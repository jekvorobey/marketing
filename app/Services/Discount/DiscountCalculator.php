<?php

namespace App\Services\Discount;

use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCategory;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountOffer;
use App\Models\Discount\DiscountSegment;
use App\Models\Discount\DiscountUserRole;
use App\Models\Price\Price;
use App\Models\Discount\Discount;
use Pim\Dto\CategoryDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\CategoryService\CategoryService;
use Pim\Services\ProductService\ProductService;
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
     * Список категорий
     * @var CategoryDto[]|Collection
     */
    protected $categories;

    /**
     * DiscountCalculator constructor.
     * @param Collection $user ['id' => int, 'role' => int, 'segment' => int]
     * @param Collection $offers [['id' => int, 'quantity' => float|null], ...]]
     * @param Collection $promoCode todo
     * @param Collection $delivery ['method' => int, 'price' => float, 'region' => int]
     * @param Collection $payment ['method' => int]
     * @param Collection $basket ['price' => float]
     */
    public function __construct(Collection $user,
                                Collection $offers,
                                Collection $promoCode,
                                Collection $delivery,
                                Collection $payment,
                                Collection $basket)
    {
        $this->filter = [];
        $this->filter['user'] = $user;
        $this->filter['offers'] = $offers;
        $this->filter['promoCode'] = $promoCode;
        $this->filter['delivery'] = $delivery;
        $this->filter['payment'] = $payment;
        $this->filter['basket'] = $basket;

        $this->loadData();
        $this->discounts = collect();
        $this->relations = collect();
        $this->categories = collect();
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
     * Загружает все необходимые данные
     * @return $this
     */
    protected function loadData()
    {
        $offerIds = $this->filter['offers']->pluck('id');
        if ($offerIds->isNotEmpty()) {
            $this->hydrateOfferPrice();
            $this->hydrateProductInfo();
        } else {
            $this->filter['offers'] = collect();
        }

        $this->filter['brands'] = $this->filter['offers']->pluck('brand_id')
            ->unique()
            ->filter(function ($brandId) {
                return $brandId > 0;
            });

        $this->filter['categories'] = $this->filter['offers']->pluck('category_id')
            ->unique()
            ->filter(function ($brandId) {
                return $brandId > 0;
            });

        return $this;
    }

    /**
     * Заполняет цены офферов
     * @return $this
     */
    protected function hydrateOfferPrice()
    {
        $offerIds = $this->filter['offers']->pluck('id');
        /** @var Collection $prices */
        $prices = Price::select(['offer_id', 'price'])
            ->whereIn('offer_id', $offerIds)
            ->get()
            ->pluck('price', 'offer_id');

        $offers = collect();
        foreach ($this->filter['offers'] as $offer) {
            $offerId = (int)$offer['id'];
            if (!$prices->has($offerId)) {
                continue;
            }

            $offers->put($offerId, collect([
                'id' => $offerId,
                'price' => $prices[$offerId],
                'quantity' => $offer['quantity'] ?? null,
                'brand_id' => $offer['brand_id'] ?? null,
                'category_id' => $offer['category_id'] ?? null,
            ]));
        }
        $this->filter['offers'] = $offers;
        return $this;
    }

    /**
     * Заполняет информацию о товаре (категория, бренд)
     * @return $this
     */
    protected function hydrateProductInfo()
    {
        $offerIds = $this->filter['offers']->pluck('id')->toArray();
        /** @var ProductService $offerService */
        $productService = resolve(ProductService::class);
        $productQuery = $productService
            ->newQuery()
            ->addFields(
                ProductDto::entity(),
                'id',
                'category_id',
                'brand_id'
            );

        $offers = collect();
        $productsByOffers = $productService->productsByOffers($productQuery, $offerIds);
        foreach ($this->filter['offers'] as $offer) {
            $offerId = $offer['id'];
            if (!isset($productsByOffers[$offerId]['product'])) {
                continue;
            }

            $product = $productsByOffers[$offerId]['product'];
            $offers->put($offerId, collect([
                'id' => $offerId,
                'price' => $offer['price'] ?? null,
                'quantity' => $offer['quantity'] ?? null,
                'brand_id' => $product['brand_id'],
                'category_id' => $product['category_id'],
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
                    # Скидка на офферы
                    $offerIds = $this->relations['offers'][$discount->id]->pluck('offer_id');
                    $changed = $this->applyDiscountToOffer($discount, $offerIds);
                    break;
                case Discount::DISCOUNT_TYPE_BUNDLE:
                    // todo
                    break;
                case Discount::DISCOUNT_TYPE_BRAND:
                    # Скидка на бренды
                    /** @var Collection $brandIds */
                    $brandIds = $this->relations['brands'][$discount->id]->pluck('brand_id');
                    # За исключением офферов
                    $exceptOfferIds = $this->getExceptOffersForDiscount($discount->id);
                    # Отбираем нужные офферы
                    $offerIds = $this->filterForDiscountBrand($brandIds, $exceptOfferIds);
                    $changed = $this->applyDiscountToOffer($discount, $offerIds);
                    break;
                case Discount::DISCOUNT_TYPE_CATEGORY:
                    # Скидка на категории
                    /** @var Collection $categoryIds */
                    $categoryIds = $this->relations['categories'][$discount->id]->pluck('category_id');
                    # За исключением брендов
                    $exceptBrandIds = $this->getExceptBrandsForDiscount($discount->id);
                    # За исключением офферов
                    $exceptOfferIds = $this->getExceptOffersForDiscount($discount->id);
                    # Отбираем нужные офферы
                    $offerIds = $this->filterForDiscountCategory($categoryIds, $exceptBrandIds, $exceptOfferIds);
                    $changed = $this->applyDiscountToOffer($discount, $offerIds);
                    break;
                case Discount::DISCOUNT_TYPE_DELIVERY:
                    $changed = $this->changePrice($this->filter['delivery'], $discount);
                    break;
                case Discount::DISCOUNT_TYPE_CART_TOTAL:
                    $changed = $this->changePrice($this->filter['basket'], $discount);
                    break;
            }

            if ($changed) {
                $this->appliedDiscounts->push($data);
            }
        }

        return $this;
    }

    /**
     * Применяет скидку к офферам
     *
     * @param $discount
     * @param Collection $offerIds
     * @return bool Результат применения скидки
     */
    protected function applyDiscountToOffer($discount, Collection $offerIds)
    {
        if ($offerIds->isEmpty()) {
            return false;
        }

        $changed = false;
        foreach ($offerIds as $offerId) {
            if (!$this->filter['offers']->has($offerId)) {
                continue;
            }

            $offer = &$this->filter['offers'][$offerId];
            $changed = $changed || $this->changePrice($offer, $discount);
        }

        return $changed;
    }

    /**
     * @param $item
     * @param $discount
     * @return bool
     */
    protected function changePrice(&$item, $discount)
    {
        if (!isset($item['price'])) {
            return false;
        }

        $currentDiscount = $item['discount'] ?? 0;
        $currentCost = $item['cost'] ?? $item['price'];
        switch ($discount->value_type) {
            case Discount::DISCOUNT_VALUE_TYPE_PERCENT:
                $item['discount'] = $currentDiscount + round($item['price'] * $discount->value / 100);
                $item['price'] = $currentCost - $item['discount'];
                $item['cost'] = $currentCost;
                break;
            case Discount::DISCOUNT_VALUE_TYPE_RUB:
                $newDiscount = $discount->value > $item['price'] ? $item['price'] : $discount->value;
                $item['discount'] = $currentDiscount + $newDiscount;
                $item['price'] = $currentCost - $item['discount'];
                $item['cost'] = $currentCost;
                break;
            default:
                return false;
        }

        return true;
    }

    /**
     * @param $brandIds
     * @param $exceptOfferIds
     * @return Collection
     */
    protected function filterForDiscountBrand($brandIds, $exceptOfferIds)
    {
        return $this->filter['offers']->filter(function ($offer) use ($brandIds, $exceptOfferIds) {
            return $brandIds->search($offer['brand_id']) !== false
                && $exceptOfferIds->search($offer['id']) === false;
        })->pluck('id');
    }

    /**
     * @param $categoryIds
     * @param $exceptBrandIds
     * @param $exceptOfferIds
     * @return Collection
     */
    protected function filterForDiscountCategory($categoryIds, $exceptBrandIds, $exceptOfferIds)
    {
        return $this->filter['offers']->filter(function ($offer) use ($categoryIds, $exceptBrandIds, $exceptOfferIds) {
            $offerCategory = $this->categories->has($offer['category_id'])
                ? $this->categories[$offer['category_id']]
                : null;

            return $offerCategory
                && $categoryIds->reduce(function ($carry, $categoryId) use ($offerCategory) {
                    return $carry ||
                        (
                            $this->categories->has($categoryId)
                            && $this->categories[$categoryId]->isAncestorOf($offerCategory)
                        );
                })
                && $exceptBrandIds->search($offer['brand_id']) === false
                && $exceptOfferIds->search($offer['id']) === false;
        })->pluck('id');
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
        if ($this->filter['brands']->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
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
        if ($this->filter['categories']->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['categories'] = collect();
            return $this;
        }

        $this->relations['categories'] = DiscountCategory::select(['discount_id', 'category_id'])
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
     * Получает все категории
     * @return $this
     */
    protected function fetchCategories()
    {
        if ($this->categories->isNotEmpty()) {
            return $this;
        }

        $categoryService = resolve(CategoryService::class);
        $this->categories = $categoryService->categories($categoryService->newQuery())->keyBy('id');
        return $this;
    }

    /**
     * @param $discountId
     * @return Collection
     */
    protected function getExceptOffersForDiscount($discountId)
    {
        return $this->relations['offers']->has($discountId)
            ? $this->relations['offers'][$discountId]->filter(function ($offer) {
                return $offer['except'];
            })->pluck('offer_id')
            : collect();
    }

    /**
     * @param $discountId
     * @return Collection
     */
    protected function getExceptBrandsForDiscount($discountId)
    {
        return $this->relations['brands']->has($discountId)
            ? $this->relations['brands'][$discountId]->filter(function ($brand) {
                return $brand['except'];
            })->pluck('brand_id')
            : collect();
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
            && $this->relations['categories'][$discount->id]->isNotEmpty();
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
                /** Скидка на заказ от заданной суммы на один из брендов */
                case DiscountCondition::MIN_PRICE_BRAND:
                    $r = $this->getMaxTotalPriceForBrands($condition->getBrands()) >= $condition->getMinPrice();
                    break;
                /** Скидка на заказ от заданной суммы на одну из категорий */
                case DiscountCondition::MIN_PRICE_CATEGORY:
                    $this->fetchCategories();
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
                case DiscountCondition::DISCOUNT_SYNERGY:
                    continue(2); # Проверяет отдельно на этапе применения скидок
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
     * Заказ от определенной суммы
     * @return float
     */
    public function getPriceOrders()
    {
        return $this->filter['offers']->pluck('price')->sum();
    }

    /**
     * Возвращает максимальную сумму товаров среди брендов ($brands)
     * @param array $brands
     * @return float
     */
    public function getMaxTotalPriceForBrands($brands)
    {
        $max = 0;
        foreach ($brands as $brandId) {
            $sum = $this->filter['offers']->filter(function ($offer) use ($brandId) {
                return $offer['brand_id'] === $brandId;
            })->pluck('price')->sum();
            $max = max($sum, $max);
        }

        return $max;
    }

    /**
     * @param array $categories
     * @return float
     */
    public function getMaxTotalPriceForCategories($categories)
    {
        $max = 0;
        foreach ($categories as $categoryId) {
            $sum = $this->filter['offers']->filter(function ($offer) use ($categoryId) {
                return $this->categories->has($categoryId)
                    && $this->categories->has($offer['category_id'])
                    && $this->categories[$categoryId]->isSelfOrAncestorOf($this->categories[$offer['category_id']]);
            })->pluck('price')->sum();
            $max = max($sum, $max);
        }

        return $max;
    }

    /**
     * Количество единиц одного оффера
     * @param $offerId
     * @param $count
     * @return bool
     */
    public function checkEveryUnitProduct($offerId, $count)
    {
        return $this->filter['offers']->has($offerId) && $this->filter['offers'][$offerId]['quantity'] >= $count;
    }

    /**
     * Способ доставки
     * @param $deliveryMethod
     * @return bool
     */
    public function checkDeliveryMethod($deliveryMethod)
    {
        return isset($this->filter['delivery']['method']) && $this->filter['delivery']['method'] === $deliveryMethod;
    }

    /**
     * Способ оплаты
     * @param $payment
     * @return bool
     */
    public function checkPayMethod($payment)
    {
        return isset($this->filter['payment']['method']) && $this->filter['payment']['method'] === $payment;
    }

    /**
     * Регион доставки
     * @param $region
     * @return bool
     */
    public function checkRegion($region)
    {
        return isset($this->filter['delivery']['region']) && $this->filter['delivery']['region'] === $region;
    }
}
