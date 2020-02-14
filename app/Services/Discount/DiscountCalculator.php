<?php

namespace App\Services\Discount;

use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCategory;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountOffer;
use App\Models\Discount\DiscountSegment;
use App\Models\Discount\DiscountUserRole;
use App\Models\Price\Price;
use Illuminate\Http\Request;
use App\Models\Discount\Discount;
use Illuminate\Support\Collection;

/**
 * Class DiscountCalculator
 * @package App\Core\Discount
 */
class DiscountCalculator
{
    /**
     * Входные условия, влияющие на получения скидки
     * @var Collection|Collection[]
     */
    protected $filter;

    /**
     * Скидки, которые активированы с помощью промокода
     * @var Collection
     */
    protected $appliedDiscounts;

    /**
     * Данные подгружаемые из зависимостей Discount
     * @var Collection|Collection[]
     */
    protected $relations;

    /**
     * Список скидок, которые можно применить (отдельно друг от друга)
     * @var Collection
     */
    protected $discounts;

    /**
     * DiscountCalculator constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->filter = $this->getFilter($request);
        $this->loadOfferData();

        $this->discounts = collect();
        $this->relations = collect();
        $this->appliedDiscounts = collect();
    }

    /**
     * Возвращает данные о примененных скидках
     *
     * @return array
     */
    public function calculate()
    {
        $this->getActiveDiscounts()
            ->fetchData()
            ->filter()
            ->sort()
            ->apply();

        return [
            'discounts' => $this->discounts,
            'apply' => $this->appliedDiscounts,
            'offers' => $this->filter['offers'],
        ];
    }

    /**
     * @param Request $request
     * @return Collection|Collection[]
     */
    protected function getFilter(Request $request)
    {
        $filter['user'] = collect($request->post('user', []));
        $filter['offers'] = collect($request->post('offers', []));
        $filter['promoCode'] = collect($request->post('promo_code', []));
        $filter['delivery'] = collect($request->post('delivery', []));
        $filter['payment'] = collect($request->post('payment', []));
        $filter['brands'] = collect(); // todo
        $filter['categories'] = collect(); // todo

        return $filter;
    }

    /**
     * Загружает данные по офферам
     * @return $this
     */
    protected function loadOfferData()
    {
        $offerIds = $this->filter['offers']->pluck('id');
        if ($offerIds->isEmpty()) {
            $this->filter['offers'] = collect();
            return $this;
        }

        /** @var Collection $prices */
        $prices = Price::select(['offer_id', 'price'])
            ->whereIn('offer_id', $offerIds)
            ->get()
            ->pluck('price', 'offer_id');

        $offers = collect();
        foreach ($this->filter['offers'] as $offer) {
            $offerId = (int) $offer['id'];
            if (!$prices->has($offerId)) {
                continue;
            }

            $offers->put($offerId, collect([
                'id' => $offerId,
                'price' => $prices[$offerId],
                'quantity' => $offer['quantity'] ?? null,
            ]));
        }
        $this->filter['offers'] = $offers;

        return $this;
    }

    /**
     * Получить все активные скидки
     *
     * @return $this
     */
    protected function getActiveDiscounts()
    {
        $this->discounts = Discount::select(['id', 'type', 'value', 'value_type', 'promo_code_only'])
            ->active()
            ->orderBy('promo_code_only')
            ->orderBy('type')
            ->get();

        return $this;
    }

    /**
     * Загружает необходимые данные о полученных скидках ($this->discount)
     * @return $this
     */
    protected function fetchData()
    {
        $this->fetchDiscountOffers()
            ->fetchDiscountBrands()
            ->fetchDiscountCategories()
            ->fetchDiscountSegments()
            ->fetchDiscountUserRoles();

        return $this;
    }

    /**
     * Фильтрует все актуальные скидки и оставляет только те, которые можно применить
     *
     * @return $this
     */
    protected function filter()
    {
        $this->discounts = $this->discounts->filter(function (Discount $discount) {
            return $this->checkDiscount($discount);
        })->values();

        $discountIds = $this->discounts->pluck('id');
        $this->fetchDiscountConditions($discountIds);
        $this->discounts = $this->discounts->filter(function (Discount $discount) {
            if ($this->relations['conditions']->has($discount->id)) {
                return $this->checkConditions($this->relations['conditions'][$discount->id]);
            }
            return true;
        })->values();

        return $this;
    }

    /**
     * Сортирует скидки согласно приоритету
     * Скидки, имеющие наибольший приоритет помещаются в начало списка
     *
     * @return $this
     * @todo
     */
    protected function sort()
    {
        /**
         * На данный момент скидки сортируются по типу скидки
         * Если две скидки имеют одинаковый тип, то сначала берется первая по списку
         */
        return $this;
    }

    /**
     * Применяет скидки
     *
     * @return $this
     */
    protected function apply()
    {
        /** @var Discount $discount */
        foreach ($this->discounts as $discount) {
            if (!$this->isCompatible($discount)) {
                continue;
            }

            $data = [
                'id' => $discount->id,
                'type' => $discount->type,
                'value' => $discount->value,
                'value_type' => $discount->value_type,
            ];

            $changed = false;
            switch ($discount->type) {
                case Discount::DISCOUNT_TYPE_OFFER:
                    $offerIds = $this->relations['offers'][$discount->id]->pluck('offer_id');
                    $changed = $this->applyDiscountToOffer($discount, $offerIds);
                    break;
                case Discount::DISCOUNT_TYPE_BUNDLE:
                    // todo
                    break;
                case Discount::DISCOUNT_TYPE_BRAND:
                    $data['brands'] = $this->relations['brands'][$discount->id]->pluck('brand_id');
                    break;
                case Discount::DISCOUNT_TYPE_CATEGORY:
                    // todo
                    break;
                case Discount::DISCOUNT_TYPE_DELIVERY:
                    // todo
                    break;
                case Discount::DISCOUNT_TYPE_CART_TOTAL:
                    // todo
                    break;
            }

            if ($changed) {
                $this->appliedDiscounts->push($data);
            }
        }

        return $this;
    }

    /**
     * Применяет скидку на офферы
     *
     * @param $discount
     * @param $offerIds
     * @return bool Результат применения скидки
     */
    protected function applyDiscountToOffer($discount, $offerIds)
    {
        $changed = false;
        foreach ($offerIds as $offerId) {
            if (!$this->filter['offers']->has($offerId)) {
                continue;
            }

            $offer = &$this->filter['offers'][$offerId];
            $currentDiscount = $offer['discount'] ?? 0;
            $currentCost = $offer['cost'] ?? $offer['price'];
            switch ($discount->value_type) {
                case Discount::DISCOUNT_VALUE_TYPE_PERCENT:
                    $offer['discount'] = $currentDiscount + round($offer['price'] * $discount->value / 100);
                    $offer['price'] = $currentCost - $offer['discount'];
                    $offer['cost'] = $currentCost;
                    break;
                case Discount::DISCOUNT_VALUE_TYPE_RUB:
                    $newDiscount = $discount->value > $offer['price']
                            ? $offer['price']
                            : $discount->value;

                    $offer['discount'] = $currentDiscount + $newDiscount;
                    $offer['price'] = $currentCost - $offer['discount'];
                    $offer['cost'] = $currentCost;
                    break;
            }

            $changed = true;
        }

        return $changed;
    }

    /**
     * @param Discount $discount
     * @return bool
     */
    protected function isCompatible(Discount $discount)
    {
        if ($this->appliedDiscounts->isEmpty()) {
            return true;
        }

        if (!$this->relations['conditions']->has($discount->id)) {
            return false;
        }

        $discountIds = $this->appliedDiscounts->pluck('id');

        /** @var DiscountCondition $condition */
        foreach ($this->relations['conditions'][$discount->id] as $condition) {
            if ($condition->type === DiscountCondition::DISCOUNT_SYNERGY) {
                $synergyDiscountIds = $condition->getSynergy();
                return $discountIds->intersect($synergyDiscountIds)->count() === $discountIds->count();
            }
        }

        return false;
    }

    /**
     * Получаем все возможные скидки и офферы из DiscountOffer
     * @return $this
     */
    protected function fetchDiscountOffers()
    {
        $validTypes = [Discount::DISCOUNT_TYPE_OFFER, Discount::DISCOUNT_TYPE_BRAND, Discount::DISCOUNT_TYPE_CATEGORY];
        if ($this->filter['offers']->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['offers'] = collect();
            return $this;
        }

        $this->relations['offers'] = DiscountOffer::select(['discount_id', 'offer_id', 'except'])
            ->whereIn('offer_id', $this->filter['offers']->pluck('id'))
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получаем все возможные скидки и бренды из DiscountBrand
     * @return $this
     */
    protected function fetchDiscountBrands()
    {
        /** Если не передали офферы, то пропускаем скидки на бренды */
        $validTypes = [Discount::DISCOUNT_TYPE_BRAND, Discount::DISCOUNT_TYPE_CATEGORY];
        if ($this->filter['offers']->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['brands'] = collect();
            return $this;
        }

        $this->relations['brands'] = DiscountBrand::select(['discount_id', 'brand_id', 'except'])
            ->whereIn('brand_id', $this->filter['brands'])
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получаем все возможные скидки и категории из DiscountCategory
     * @return $this
     */
    protected function fetchDiscountCategories()
    {
        /** Если не передали офферы, то пропускаем скидки на категорию */
        $validTypes = [Discount::DISCOUNT_TYPE_CATEGORY];
        if ($this->filter['offers']->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['categories'] = collect();
            return $this;
        }

        $this->relations['categories'] = DiscountCategory::select(['discount_id', 'category_id', 'except'])
            ->whereIn('category_id', $this->filter['categories'])
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получаем все возможные скидки и сегменты из DiscountSegment
     * @return $this
     */
    protected function fetchDiscountSegments()
    {
        $this->relations['segments'] = DiscountSegment::select(['discount_id', 'segment_id'])
            ->get()
            ->groupBy('discount_id')
            ->transform(function ($segments) {
                return $segments->pluck('segment_id');
            });

        return $this;
    }

    /**
     * Получаем все возможные скидки и роли из DiscountRole
     * @return $this
     */
    protected function fetchDiscountUserRoles()
    {
        $this->relations['roles'] = DiscountUserRole::select(['discount_id', 'role_id'])
            ->get()
            ->groupBy('discount_id')
            ->transform(function ($segments) {
                return $segments->pluck('role_id');
            });

        return $this;
    }

    /**
     * Получаем все возможные скидки и условия из DiscountCondition
     * @param Collection $discountIds
     * @return $this
     */
    protected function fetchDiscountConditions(Collection $discountIds)
    {
        $this->relations['conditions'] = DiscountCondition::query()
            ->whereIn('discount_id', $discountIds)
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Существует ли хотя бы одна скидка с одним из типов скидки ($types)
     * @param array $types
     * @return bool
     */
    protected function existsAnyTypeInDiscounts(array $types): bool
    {
        return $this->discounts->groupBy('type')
            ->keys()
            ->intersect($types)
            ->isEmpty();
    }

    /**
     * Можно ли применить данную скидку (независимо от других скидок)
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkDiscount(Discount $discount): bool
    {
        return $this->checkPromo($discount)
            && $this->checkType($discount)
            && $this->checkUserRole($discount)
            && $this->checkSegment($discount);
    }

    /**
     * Если скидку можно получить только по промокоду, проверяем введен ли нужный промокод
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkPromo(Discount $discount): bool
    {
        return !$discount->promo_code_only || $this->appliedDiscounts->search($discount->id) !== false;
    }

    /**
     * Проверяет все необходимые условия по свойству "Тип скидки"
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkType(Discount $discount): bool
    {
        switch ($discount->type) {
            case Discount::DISCOUNT_TYPE_OFFER:
                return $this->checkOffers($discount);
            case Discount::DISCOUNT_TYPE_BUNDLE:
                return $this->checkBundles($discount);
            case Discount::DISCOUNT_TYPE_BRAND:
                return $this->checkBrands($discount);
            case Discount::DISCOUNT_TYPE_CATEGORY:
                return $this->checkCategories($discount);
            case Discount::DISCOUNT_TYPE_DELIVERY:
            case Discount::DISCOUNT_TYPE_CART_TOTAL:
                return true;
            default:
                return false;
        }
    }

    /**
     * Проверяет доступность применения скидки на офферы
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkOffers(Discount $discount): bool
    {
        return $discount->type === Discount::DISCOUNT_TYPE_OFFER
            && $this->relations['offers']->has($discount->id)
            && $this->relations['offers'][$discount->id]->filter(function ($offers) {
                return !$offers['except'];
            })->isNotEmpty();
    }

    /**
     * Проверяет доступность применения скидки на бандлы
     *
     * @param Discount $discount
     * @return bool
     * @todo
     */
    protected function checkBundles(Discount $discount): bool
    {
        return $discount->type === Discount::DISCOUNT_TYPE_BUNDLE;
    }

    /**
     * Проверяет доступность применения скидки на бренды
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkBrands(Discount $discount): bool
    {
        return $discount->type === Discount::DISCOUNT_TYPE_BRAND
            && $this->relations['brands']->has($discount->id)
            && $this->relations['brands'][$discount->id]->filter(function ($brand) {
                return !$brand['except'];
            })->isNotEmpty();
    }

    /**
     * Проверяет доступность применения скидки на категории
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkCategories(Discount $discount): bool
    {
        return $discount->type === Discount::DISCOUNT_TYPE_CATEGORY
            && $this->relations['categories']->has($discount->id)
            && $this->relations['categories'][$discount->id]->filter(function ($category) {
                return !$category['except'];
            })->isNotEmpty();
    }

    /**
     * @param Discount $discount
     * @return bool
     */
    protected function checkUserRole(Discount $discount): bool
    {
        // Если отсутствуют условия скидки на пользовательскую роль
        if (!$this->relations['roles']->has($discount->id)) {
            return true;
        }

        return isset($this->filter['user']['role'])
            && $this->relations['roles'][$discount->id]->search($this->filter['user']['role']) !== false;

    }

    /**
     * @param Discount $discount
     * @return bool
     */
    protected function checkSegment(Discount $discount): bool
    {
        // Если отсутствуют условия скидки на сегмент
        if (!$this->relations['segments']->has($discount->id)) {
            return true;
        }

        return isset($this->filter['user']['segment'])
            && $this->relations['segments'][$discount->id]->search($this->filter['user']['segment']) !== false;
    }

    /**
     * Проверяет доступность применения скидки на все соответствующие условия
     *
     * @param Collection $conditions
     * @return bool
     * @todo
     */
    protected function checkConditions(Collection $conditions): bool
    {
        /** @var DiscountCondition $condition */
        foreach ($conditions as $condition) {
            switch ($condition->type) {
                /** Скидка на первый заказ */
                case DiscountCondition::FIRST_ORDER:
                    $r = $this->getCountOrders() === 0;
                    break;
                /** Скидка на заказ от заданной суммы */
                case DiscountCondition::MIN_PRICE_ORDER:
                    $r = $this->getPriceOrders() >= $condition->getMinPrice();
                    break;
                /** Скидка на заказ от заданной суммы на один из бренд */
                case DiscountCondition::MIN_PRICE_BRAND:
                    $r = $this->getMaxTotalPriceForBrands($condition->getBrands()) >= $condition->getMinPrice();
                    break;
                /** Скидка на заказ от заданной суммы на одну из категорий */
                case DiscountCondition::MIN_PRICE_CATEGORY:
                    $r = $this->getMaxTotalPriceForCategories($condition->getCategories()) >= $condition->getMinPrice();
                    break;
                /** Скидка на заказ определенного количества товара */
                case DiscountCondition::EVERY_UNIT_PRODUCT:
                    $r = $this->checkEveryUnitProduct($condition->getOffer(), $condition->getCount());
                    break;
                /** Скидка на один из методов доставки */
                case DiscountCondition::DELIVERY_METHOD:
                    $r = $this->checkDeliveryMethod($condition->getDeliveryMethods());
                    break;
                /** Скидка на один из методов оплаты */
                case DiscountCondition::PAY_METHOD:
                    $r = $this->checkPayMethod($condition->getPaymentMethods());
                    break;
                /** Скидка при заказе в один из регионов */
                case DiscountCondition::REGION:
                    $r = $this->checkRegion($condition->getRegions());
                    break;
                case DiscountCondition::USER:
                    $r = in_array($this->getUserId(), $condition->getUserIds());
                    break;
                /** Скидка на каждый N-й заказ */
                case DiscountCondition::ORDER_SEQUENCE_NUMBER:
                    $r = $this->getCountOrders() % $condition->getOrderSequenceNumber() === 0;
                    break;
                case DiscountCondition::BUNDLE:
                    $r = true; // todo
                    break;
                default:
                    return false;
            }

            if (!$r) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return int|null
     */
    public function getUserId()
    {
        return $this->filter['user']['id'] ?? null;
    }

    /**
     * @return int
     * @todo
     */
    public function getCountOrders()
    {
        return 0;
    }

    /**
     * @return float
     * @todo
     */
    public function getPriceOrders()
    {
        return 0;
    }

    /**
     * @param array $brands
     * @return float
     * @todo
     */
    public function getMaxTotalPriceForBrands($brands)
    {
        return 0;
    }

    /**
     * @param array $categories
     * @return float
     * @todo
     */
    public function getMaxTotalPriceForCategories($categories)
    {
        return 0;
    }

    /**
     * @param $offerId
     * @param $count
     * @return bool
     * @todo
     */
    public function checkEveryUnitProduct($offerId, $count)
    {
        return true;
    }

    /**
     * @param $deliveryMethod
     * @return bool
     * @todo
     */
    public function checkDeliveryMethod($deliveryMethod)
    {
        return true;
    }

    /**
     * @param $payment
     * @return bool
     * @todo
     */
    public function checkPayMethod($payment)
    {
        return true;
    }

    /**
     * @param $region
     * @return bool
     * @todo
     */
    public function checkRegion($region)
    {
        return true;
    }
}
