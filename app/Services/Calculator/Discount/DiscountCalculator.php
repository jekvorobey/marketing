<?php

namespace App\Services\Calculator\Discount;

use App\Models\Discount\BundleItem;
use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\Discount\Applier\BasketApplier;
use App\Services\Calculator\Discount\Applier\DeliveryApplier;
use App\Services\Calculator\Discount\Applier\OfferApplier;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;
use Illuminate\Support\Collection;

/**
 * Class DiscountCalculator
 * @package App\Services\Calculator\Discount
 */
class DiscountCalculator extends AbstractCalculator
{
    /**
     * Скидки, которые активированы с помощью промокода
     * @var Collection
     */
    protected $appliedDiscounts;

    /**
     * Список скидок, которые можно применить (отдельно друг от друга)
     * @var Collection
     */
    protected $possibleDiscounts;

    /**
     * Офферы со скидками в формате:
     * [offer_id => [['id' => discount_id, 'value' => value, 'value_type' => value_type], ...]]
     * @var Collection
     */
    protected $offersByDiscounts;

    /**
     * Список активных скидок
     * @var Collection
     */
    protected $discounts;

    /**
     * Данные подгружаемые из зависимостей Discount
     * @var Collection|Collection[]
     */
    protected $relations;

    /**
     * DiscountCalculator constructor.
     */
    public function __construct(InputCalculator $inputCalculator, OutputCalculator $outputCalculator)
    {
        parent::__construct($inputCalculator, $outputCalculator);

        $this->discounts = collect();
        $this->relations = collect();
        $this->appliedDiscounts = collect();
        $this->possibleDiscounts = collect();
        $this->offersByDiscounts = collect();
    }

    public function calculate()
    {
        $this->fetchDiscountsAndRelations();

        if (!empty($this->input->deliveries['items'])) {
            if (!$this->input->freeDelivery) {
                /**
                 * Считаются только возможные скидки.
                 * Берем все доставки, для которых необходимо посчитать только возможную скидку,
                 * по очереди применяем скидки (откатывая предыдущие изменяния, т.к. нельзя выбрать сразу две доставки),
                 */
                foreach ($this->input->deliveries['notSelected'] as $delivery) {
                    $deliveryId = $delivery['id'];
                    $this->input->deliveries['current'] = $delivery;
                    $this->filter()->sort()->apply();
                    $deliveryWithDiscount = $this->input->deliveries['items'][$deliveryId];
                    $this->rollback();
                    $this->input->deliveries['items'][$deliveryId] = $deliveryWithDiscount;
                }
            }

            $this->input->deliveries['current'] = $this->input->deliveries['items']->filter(function ($item) {
                return $item['selected'];
            })->first();
        }

        /** Считаются окончательные скидки + бонусы */
        $this->filter()->sort()->apply();

        $this->getDiscountOutput();
    }

    private function fetchDiscountsAndRelations()
    {
        $discountFetcher = new DiscountFetcher($this->input);
        $this->discounts = $discountFetcher->getDiscounts();
        $this->relations = $discountFetcher->getRelations();
    }

    private function getDiscountOutput(): void
    {
        $discountOutput = new DiscountOutput($this->input, $this->discounts, $this->relations, $this->offersByDiscounts, $this->appliedDiscounts);
        $this->input->offers = $discountOutput->getOffers();
        $this->output->appliedDiscounts = $discountOutput->getOutputFormat();
    }

    /**
     * Полностью откатывает все примененные скидки
     * @return $this
     */
    public function forceRollback()
    {
        return $this->rollback();
    }

    /**
     * Применяет скидки
     * @return $this
     */
    protected function apply()
    {
        /** @var Discount $discount */
        foreach ($this->possibleDiscounts as $discount) {
            $this->applyDiscount($discount);
        }

        return $this;
    }

    /**
     * @return int|bool – На сколько изменилась цена (false – скидку невозможно применить)
     */
    protected function applyDiscount(Discount $discount)
    {
        if (!$this->isCompatibleDiscount($discount)) {
            return false;
        }

        $change = false;
        switch ($discount->type) {
            case Discount::DISCOUNT_TYPE_OFFER:
                # Скидка на определенные офферы
                $offerIds = $this->relations['offers'][$discount->id]->pluck('offer_id');

                $offerApplier = new OfferApplier($this->input, $this->offersByDiscounts, $this->appliedDiscounts, $this->relations);
                $offerApplier->setOfferIds($offerIds);
                $change = $offerApplier->apply($discount);
                break;
            case Discount::DISCOUNT_TYPE_ANY_OFFER:
                # Скидка на все товары
                $offerIds = $this->input->offers
                    ->where('product_id', '!=', null)
                    ->pluck('id');

                $offerApplier = new OfferApplier($this->input, $this->offersByDiscounts, $this->appliedDiscounts, $this->relations);
                $offerApplier->setOfferIds($offerIds);
                $change = $offerApplier->apply($discount);
                break;
            case Discount::DISCOUNT_TYPE_BUNDLE_OFFER:
            case Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS:
            case Discount::DISCOUNT_TYPE_ANY_BUNDLE:
                # Скидка на бандлы
                # Определяем id офферов по бандлам
                /** @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter */
                $offerIds = $discount->type == Discount::DISCOUNT_TYPE_BUNDLE_OFFER ||
                    $discount->type == Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS
                    ? $this->relations['bundleItems'][$discount->id]->pluck('item_id')
                    : collect($this->relations['bundleItems'])->filter(function ($items, $discountId) {
                        return $this->input->bundles->has($discountId);
                    })
                        ->collapse()
                        ->map(function (BundleItem $item) {
                            return [
                                'item_id' => $item->item_id,
                                'bundle_id' => $item->discount_id,
                            ];
                        });

                if ($discount->type == Discount::DISCOUNT_TYPE_BUNDLE_OFFER) {
                    $offerApplier = new OfferApplier($this->input, $this->offersByDiscounts, $this->appliedDiscounts, $this->relations);
                    $offerApplier->setOfferIds($offerIds);
                    $change = $offerApplier->apply($discount);
                }

                // todo Рассчет скидки для мастерклассов и для скидки на все бандлы

                break;
            case Discount::DISCOUNT_TYPE_BRAND:
            case Discount::DISCOUNT_TYPE_ANY_BRAND:
                # Скидка на бренды
                /** @var Collection $brandIds */
                $brandIds = $discount->type == Discount::DISCOUNT_TYPE_BRAND
                    ? $this->relations['brands'][$discount->id]->pluck('brand_id')
                    : $this->input->brands;

                # За исключением офферов
                $exceptOfferIds = $this->getExceptOffersForDiscount($discount->id);
                # Отбираем нужные офферы
                $offerIds = $this->filterForBrand($brandIds, $exceptOfferIds, $discount->merchant_id);
                $offerApplier = new OfferApplier($this->input, $this->offersByDiscounts, $this->appliedDiscounts, $this->relations);
                $offerApplier->setOfferIds($offerIds);
                $change = $offerApplier->apply($discount);
                break;
            case Discount::DISCOUNT_TYPE_CATEGORY:
            case Discount::DISCOUNT_TYPE_ANY_CATEGORY:
                # Скидка на категории
                /** @var Collection $categoryIds */
                $categoryIds = $discount->type == Discount::DISCOUNT_TYPE_CATEGORY
                    ? $this->relations['categories'][$discount->id]->pluck('category_id')
                    : $this->input->categories;
                # За исключением брендов
                $exceptBrandIds = $this->getExceptBrandsForDiscount($discount->id);
                # За исключением офферов
                $exceptOfferIds = $this->getExceptOffersForDiscount($discount->id);
                # Отбираем нужные офферы
                $offerIds = $this->filterForCategory(
                    $categoryIds,
                    $exceptBrandIds,
                    $exceptOfferIds,
                    $discount->merchant_id
                );
                $offerApplier = new OfferApplier($this->input, $this->offersByDiscounts, $this->appliedDiscounts, $this->relations);
                $offerApplier->setOfferIds($offerIds);
                $change = $offerApplier->apply($discount);
                break;
            case Discount::DISCOUNT_TYPE_DELIVERY:
                // Если используется бесплатная доставка (например, по промокоду), то не использовать скидку
                if ($this->input->freeDelivery) {
                    break;
                }

                $deliveryId = $this->input->deliveries['current']['id'] ?? null;
                if ($this->input->deliveries['items']->has($deliveryId)) {
                    $currentDeliveries = $this->input->deliveries['current'];
                    $deliveryApplier = new DeliveryApplier();
                    $deliveryApplier->setCurrentDeliveries($currentDeliveries);
                    $change = $deliveryApplier->apply($discount);

                    $this->input->deliveries['current'] = $currentDeliveries;
                    $this->input->deliveries['items'][$deliveryId] = $this->input->deliveries['current'];
                }

                break;
            case Discount::DISCOUNT_TYPE_CART_TOTAL:
                $basketApplier = new BasketApplier($this->input, $this->offersByDiscounts);
                $change = $basketApplier->apply($discount);
                break;
            # Скидка на мастер-классы
            case Discount::DISCOUNT_TYPE_MASTERCLASS:
                $ticketTypeIds = $this->relations['ticketTypeIds'][$discount->id]
                    ->pluck('ticket_type_id')
                    ->toArray();

                $offerIds = $this->input->offers
                    ->whereIn('ticket_type_id', $ticketTypeIds)
                    ->pluck('id');

                $offerApplier = new OfferApplier($this->input, $this->offersByDiscounts, $this->appliedDiscounts, $this->relations);
                $offerApplier->setOfferIds($offerIds);
                $change = $offerApplier->apply($discount);
                break;
            case Discount::DISCOUNT_TYPE_ANY_MASTERCLASS:
                $offerIds = $this->input->offers
                    ->whereStrict('product_id', null)
                    ->pluck('id');

                $offerApplier = new OfferApplier($this->input, $this->offersByDiscounts, $this->appliedDiscounts, $this->relations);
                $offerApplier->setOfferIds($offerIds);
                $change = $offerApplier->apply($discount);
                break;
        }

        if ($change > 0) {
            $this->appliedDiscounts->put($discount->id, [
                'discountId' => $discount->id,
                'change' => $change,
                'conditions' => $this->relations['conditions']->has($discount->id)
                    ? $this->relations['conditions'][$discount->id]->pluck('type')
                    : [],
            ]);
        }

        return $change;
    }

    /**
     * @param $discountId
     *
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
     *
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
     * Совместимы ли скидки (даже если они не пересекаются)
     *
     *
     * @return bool
     */
    protected function isCompatibleDiscount(Discount $discount)
    {
        return !$this->appliedDiscounts->has($discount->id);
    }

    /**
     * Откатывает все примененные скидки
     * @return $this
     */
    protected function rollback()
    {
        $this->appliedDiscounts = collect();
        $this->offersByDiscounts = collect();
        $this->output->appliedDiscounts = collect();

        $offers = collect();
        foreach ($this->input->offers as $offer) {
            $offer['price'] = $offer['cost'] ?? $offer['price'];
            unset($offer['discount']);
            unset($offer['cost']);
            if (isset($offer['bundles'])) {
                $offer['bundles']->transform(function ($bundle) use ($offer) {
                    $bundle['price'] = $offer['price'];
                    unset($bundle['discount']);
                    return $bundle;
                });
            }
            $offers->put($offer['id'], $offer);
        }
        $this->input->offers = $offers;

        $deliveries = collect();
        foreach ($this->input->deliveries['items'] as $delivery) {
            $delivery['price'] = $delivery['cost'] ?? $delivery['price'];
            unset($delivery['discount']);
            unset($delivery['cost']);
            $deliveries->put($delivery['id'], $delivery);
        }
        $this->input->deliveries['items'] = $deliveries;

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
        // Все бандлы ставим в конец списка
        $this->possibleDiscounts = $this->possibleDiscounts->sortBy(function (Discount $discount) {
            return $discount->type == Discount::DISCOUNT_TYPE_BUNDLE_OFFER ||
                $discount->type == Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS ? 1 : 0;
        })
            ->values();

        return $this;
    }

    /**
     * Фильтрует все актуальные скидки и оставляет только те, которые можно применить
     *
     * @return $this
     */
    protected function filter()
    {
        $this->possibleDiscounts = $this->discounts->filter(function (Discount $discount) {
            return $this->checkDiscount($discount);
        })->values();

        $discountIds = $this->possibleDiscounts->pluck('id');
        $this->fetchDiscountConditions($discountIds);
        $this->possibleDiscounts = $this->possibleDiscounts->filter(function (Discount $discount) {
            if ($this->relations['conditions']->has($discount->id)) {
                return $this->checkConditions($this->relations['conditions'][$discount->id]);
            }

            return true;
        })->values();

        return $this;
    }

    /**
     * Можно ли применить данную скидку (независимо от других скидок)
     */
    protected function checkDiscount(Discount $discount): bool
    {
        return $this->checkType($discount)
            && $this->checkCustomerRole($discount)
            && $this->checkSegment($discount);
    }

    /**
     * Проверяет все необходимые условия по свойству "Тип скидки"
     */
    protected function checkType(Discount $discount): bool
    {
        switch ($discount->type) {
            case Discount::DISCOUNT_TYPE_OFFER:
                return $this->checkOffers($discount);
            case Discount::DISCOUNT_TYPE_BUNDLE_OFFER:
            case Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS:
                return $this->checkBundles($discount);
            case Discount::DISCOUNT_TYPE_BRAND:
                return $this->checkBrands($discount);
            case Discount::DISCOUNT_TYPE_CATEGORY:
                return $this->checkCategories($discount);
            case Discount::DISCOUNT_TYPE_DELIVERY:
                return isset($this->input->deliveries['current']['price']);
            case Discount::DISCOUNT_TYPE_MASTERCLASS:
                return $this->input->ticketTypeIds->isNotEmpty()
                    && $this->checkPublicEvents($discount);
            case Discount::DISCOUNT_TYPE_CART_TOTAL:
            case Discount::DISCOUNT_TYPE_ANY_OFFER:
            case Discount::DISCOUNT_TYPE_ANY_BUNDLE:
            case Discount::DISCOUNT_TYPE_ANY_BRAND:
            case Discount::DISCOUNT_TYPE_ANY_CATEGORY:
            case Discount::DISCOUNT_TYPE_ANY_MASTERCLASS:
                return $this->input->offers->isNotEmpty();
            default:
                return false;
        }
    }

    /**
     * Проверяет доступность применения скидки на офферы
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
     * Проверяет доступность применения скидок-бандлов
     */
    protected function checkBundles(Discount $discount): bool
    {
        return ($discount->type === Discount::DISCOUNT_TYPE_BUNDLE_OFFER ||
                $discount->type === Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS) &&
                $this->relations['bundleItems']->has($discount->id);
    }

    /**
     * Проверяет доступность применения скидки на бренды
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
     */
    protected function checkCategories(Discount $discount): bool
    {
        return $discount->type === Discount::DISCOUNT_TYPE_CATEGORY
            && $this->relations['categories']->has($discount->id)
            && $this->relations['categories'][$discount->id]->isNotEmpty();
    }

    /**
     * Проверяет доступность применения скидки на мастер-классы
     */
    protected function checkPublicEvents(Discount $discount): bool
    {
        return $discount->type === Discount::DISCOUNT_TYPE_MASTERCLASS
            && $this->relations['ticketTypeIds']->has($discount->id)
            && $this->relations['ticketTypeIds'][$discount->id]->isNotEmpty();
    }

    protected function checkCustomerRole(Discount $discount): bool
    {
        return !$this->relations['roles']->has($discount->id) ||
            (
                isset($this->input->customer['roles'])
                && $this->relations['roles'][$discount->id]->intersect($this->input->customer['roles'])->isNotEmpty()
            );
    }

    protected function checkSegment(Discount $discount): bool
    {
        // Если отсутствуют условия скидки на сегмент
        if (!$this->relations['segments']->has($discount->id)) {
            return true;
        }

        return isset($this->input->customer['segment'])
            && $this->relations['segments'][$discount->id]->search($this->input->customer['segment']) !== false;
    }

    /**
     * Проверяет доступность применения скидки на все соответствующие условия
     *
     *
     * @todo
     */
    protected function checkConditions(Collection $conditions): bool
    {
        /** @var DiscountCondition $condition */
        foreach ($conditions as $condition) {
            switch ($condition->type) {
                /** Скидка на первый заказ */
                case DiscountCondition::FIRST_ORDER:
                    $r = $this->input->getCountOrders() === 0;
                    break;
                /** Скидка на заказ от заданной суммы */
                case DiscountCondition::MIN_PRICE_ORDER:
                    $r = $this->input->getCostOrders() >= $condition->getMinPrice();
                    break;
                /** Скидка на заказ от заданной суммы на один из брендов */
                case DiscountCondition::MIN_PRICE_BRAND:
                    $r = $this->input->getMaxTotalPriceForBrands($condition->getBrands()) >= $condition->getMinPrice();
                    break;
                /** Скидка на заказ от заданной суммы на одну из категорий */
                case DiscountCondition::MIN_PRICE_CATEGORY:
                    $r = $this->input->getMaxTotalPriceForCategories($condition->getCategories()) >= $condition->getMinPrice();
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
                /** Скидка при заказе из региона */
                case DiscountCondition::REGION:
                    $r = $this->checkRegion($condition->getRegions());
                    break;
                /** Скидка для определенных покупателей */
                case DiscountCondition::CUSTOMER:
                    $r = in_array($this->input->getCustomerId(), $condition->getCustomerIds());
                    break;
                /** Скидка на каждый N-й заказ */
                case DiscountCondition::ORDER_SEQUENCE_NUMBER:
                    $countOrders = $this->input->getCountOrders();
                    $r = isset($countOrders) && (($countOrders + 1) % $condition->getOrderSequenceNumber() === 0);
                    break;
                case DiscountCondition::BUNDLE:
                    $r = true; // todo
                    break;
                case DiscountCondition::DISCOUNT_SYNERGY:
                    break 2; # Проверяет отдельно на этапе применения скидок
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
     * Регион пользователя
     *
     * @param $regions
     *
     * @return bool
     */
    public function checkRegion($regions)
    {
        return !empty($this->input->userRegion)
            ? in_array($this->input->userRegion['id'], $regions)
            : false;
    }

    /**
     * Количество единиц одного оффера
     *
     * @param $offerId
     * @param $count
     *
     * @return bool
     */
    public function checkEveryUnitProduct($offerId, $count)
    {
        return $this->input->offers->has($offerId) && $this->input->offers[$offerId]['qty'] >= $count;
    }

    /**
     * Способ доставки
     *
     * @param $deliveryMethods
     *
     * @return bool
     */
    public function checkDeliveryMethod($deliveryMethods)
    {
        return isset($this->input->deliveries['current']['method']) && in_array(
            $this->input->deliveries['current']['method'],
            $deliveryMethods
        );
    }

    /**
     * Способ оплаты
     *
     * @param $payments
     *
     * @return bool
     */
    public function checkPayMethod($payments)
    {
        return isset($this->input->payment['method']) && in_array($this->input->payment['method'], $payments);
    }

    /**
     * Существует ли хотя бы одна скидка с одним из типов скидки ($types)
     *
     * метод не совсем соответсвует названию, в случае если существует скидка с таким типом,
     * то возвращается false, хотя по названию должно возвращаться true
     *
     * @param array $types
     */
    protected function existsAnyTypeInDiscounts(array $types): bool
    {
        return $this->discounts->groupBy('type')
            ->keys()
            ->intersect($types)
            ->isEmpty();
    }

    /**
     * Получаем все возможные скидки и условия из DiscountCondition
     *
     *
     * @return $this
     */
    protected function fetchDiscountConditions(Collection $discountIds)
    {
        $this->relations['conditions'] = DiscountCondition::select(['discount_id', 'type', 'condition'])
            ->whereIn('discount_id', $discountIds)
            ->get()
            ->groupBy('discount_id');

        return $this;
    }
}
