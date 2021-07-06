<?php

namespace App\Services\Calculator\Discount;

use App\Models\Discount\BundleItem;
use App\Models\Discount\Discount;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCategory;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountOffer;
use App\Models\Discount\DiscountPublicEvent;
use App\Models\Discount\DiscountSegment;
use App\Models\Discount\DiscountUserRole;
use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;
use Illuminate\Support\Collection;
use Pim\Core\PimException;

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
     * Если по какой-то скидке есть ограничение на максимальный итоговый размер скидки по офферу
     * @var array [discount_id => ['value' => value, 'value_type' => value_type], ...]
     */
    protected $maxValueByDiscount = [];

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

    /**
     * @throws PimException
     */
    public function calculate()
    {
        $this->fetchActiveDiscounts()->fetchRelations();

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

        $this->input->offers->transform(function ($offer, $offerId) {

            if (!$this->offersByDiscounts->has($offerId)) {
                $offer['discount'] = null;
                $offer['discounts'] = null;
                return $offer;
            }
            # Конечная цена товара после применения скидки всегда округляется до целого
            $offer['price'] = round($offer['price']);

            $roundOffs = collect();
            # Погрешность после применения базовых товарных скидок (не бандлов)
            $basicError = 0;
            if (isset($offer['discount'])) {
                # Финальная скидка, в которую входит сама скидка и ошибка округления
                $finalDiscount = $offer['cost'] - $offer['price'];
                $basicError = $finalDiscount - $offer['discount'];
                $basicDiscountsNumber = $this->offersByDiscounts[$offerId]->filter(function ($discount) {
                        return !$this->input->bundles->contains($discount['id']);
                })->count();
                $diffPerDiscount = $basicDiscountsNumber > 0
                    ? round($basicError / $basicDiscountsNumber, 2)
                    : 0;
                $correctionValue = $diffPerDiscount
                    ? round($basicError - $diffPerDiscount * $basicDiscountsNumber, 3)
                    : 0;

                $roundOffs->put(0, [
                    'error' => $diffPerDiscount,
                    'correction' => $correctionValue,
                    'affectedQty' => $offer['qty'],
                ]);
            }

            $offer['bundles']->each(function ($bundle, $id) use ($roundOffs, $offer, &$basicError) {
                if ($id == 0 || !isset($bundle['discount'])) {
                    return;
                }
                $finalDiscount = $offer['cost'] - $bundle['price'];
                $roundOffError = $finalDiscount - $bundle['discount'];

                $roundOffs->put($id, [
                    'error' => round($roundOffError - $basicError, 2),
                    'correction' => 0,
                    'affectedQty' => $bundle['qty'],
                ]);
            });

            $this->offersByDiscounts[$offerId]->transform(function ($discount) use ($roundOffs) {
                $key = $roundOffs->has($discount['id']) ? $discount['id'] : 0;
                $roundOff = $roundOffs->get($key);
                if (!$roundOff) {
                    return $discount;
                }

                $resultedDiff = $roundOff['error'] + $roundOff['correction'];
                $roundOff['correction'] = 0;
                $roundOffs->put($key, $roundOff);

                $discount['change'] = round($discount['change'] + $resultedDiff, 2);

                $appliedDiscount = $this->appliedDiscounts->get($discount['id']);
                $appliedDiscount['change'] = round($appliedDiscount['change'] + $resultedDiff * $roundOff['affectedQty'], 2);
                $this->appliedDiscounts->put($discount['id'], $appliedDiscount);

                return $discount;
            });

            /** @var Collection|null $discountsWithoutBundles */
            $discountsWithoutBundles = $this->offersByDiscounts[$offerId]->filter(function ($discount) {
                    return !$this->input->bundles->contains($discount['id']);
            })->values();

            $sum = round($discountsWithoutBundles->sum('change'), 2);
            $offer['discount'] = $sum;

            $offer['discounts'] = $discountsWithoutBundles->toArray();

            /** @var Collection|null $discountsWithBundles */
            $discountsWithBundles = $this->offersByDiscounts[$offerId]->filter(function ($discount) {
                    return $this->input->bundles->contains($discount['id']);
            })->keyBy('id');

            if ($discountsWithBundles && !$discountsWithBundles->isEmpty()) {
                $offer['bundles']->transform(function ($bundle, $bundleId) use ($sum, $discountsWithBundles, $discountsWithoutBundles) {
                    if ($bundleId && $discountsWithBundles->has($bundleId)) {
                        $bundle['discount'] = $sum + $discountsWithBundles[$bundleId]['change'];
                        $bundle['discounts'] = $discountsWithoutBundles->push($discountsWithBundles[$bundleId]);
                    }
                    return $bundle;
                });
            }

            return $offer;
        });

        $this->output->appliedDiscounts = $this->getExternalDiscountFormat();
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

                $change = $this->applyDiscountToOffer($discount, $offerIds);
                break;
            case Discount::DISCOUNT_TYPE_ANY_OFFER:
                # Скидка на все товары
                $offerIds = $this->input->offers
                    ->where('product_id', '!=', null)
                    ->pluck('id');

                $change = $this->applyDiscountToOffer($discount, $offerIds);
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
                    $change = $this->applyDiscountToOffer($discount, $offerIds);
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
                $change = $this->applyDiscountToOffer($discount, $offerIds);
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
                $change = $this->applyDiscountToOffer($discount, $offerIds);
                break;
            case Discount::DISCOUNT_TYPE_DELIVERY:
                // Если используется бесплатная доставка (например, по промокоду), то не использовать скидку
                if ($this->input->freeDelivery) {
                    break;
                }

                $deliveryId = $this->input->deliveries['current']['id'] ?? null;
                if ($this->input->deliveries['items']->has($deliveryId)) {
                    $currentDeliveries = $this->input->deliveries['current'];
                    $change = $this->changePrice(
                        $currentDeliveries,
                        $discount->value,
                        $discount->value_type,
                        true,
                        self::FREE_DELIVERY_PRICE
                    );
                    $this->input->deliveries['current'] = $currentDeliveries;
                    $this->input->deliveries['items'][$deliveryId] = $this->input->deliveries['current'];
                }

                break;
            case Discount::DISCOUNT_TYPE_CART_TOTAL:
                $change = $this->applyDiscountToBasket($discount);
                break;
            # Скидка на мастер-классы
            case Discount::DISCOUNT_TYPE_MASTERCLASS:
                $ticketTypeIds = $this->relations['ticketTypeIds'][$discount->id]
                    ->pluck('ticket_type_id')
                    ->toArray();

                $offerIds = $this->input->offers
                    ->whereIn('ticket_type_id', $ticketTypeIds)
                    ->pluck('id');

                $change = $this->applyDiscountToOffer($discount, $offerIds);
                break;
            case Discount::DISCOUNT_TYPE_ANY_MASTERCLASS:
                $offerIds = $this->input->offers
                    ->whereStrict('product_id', null)
                    ->pluck('id');

                $change = $this->applyDiscountToOffer($discount, $offerIds);
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
     * Применяет скидку ко всей корзине (распределяется равномерно по всем товарам)
     *
     * @param $discount
     * @return int Абсолютный размер скидки (в руб.), который удалось использовать
     */
    protected function applyDiscountToBasket($discount)
    {
        if ($this->input->offers->isEmpty()) {
            return false;
        }

        return $this->applyEvenly($discount, $this->input->offers->pluck('id'));
    }

    /**
     * Можно ли применить скидку к офферу
     *
     * @param Discount $discount
     * @param $offerId
     *
     * @return bool
     */
    protected function applicableToOffer($discount, $offerId)
    {
        if (
            $discount->type == Discount::DISCOUNT_TYPE_BUNDLE_OFFER ||
            $discount->type == Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS ||
            $discount->type == Discount::DISCOUNT_TYPE_ANY_BUNDLE
        ) {
            return true;
        }

        if ($this->appliedDiscounts->isEmpty() || !$this->offersByDiscounts->has($offerId)) {
            return true;
        }

        if (!$this->relations['conditions']->has($discount->id)) {
            return false;
        }

        /** @var Collection $discountIdsForOffer */
        $discountIdsForOffer = $this->offersByDiscounts[$offerId]->pluck('id');

        /** @var DiscountCondition $condition */
        foreach ($this->relations['conditions'][$discount->id] as $condition) {
            if ($condition->type === DiscountCondition::DISCOUNT_SYNERGY) {
                $synergyDiscountIds = $condition->getSynergy();
                if ($discountIdsForOffer->intersect($synergyDiscountIds)->count() !== $discountIdsForOffer->count()) {
                    return false;
                }

                if ($condition->getMaxValueType()) {
                    $this->maxValueByDiscount[$discount->id] = [
                        'value_type' => $condition->getMaxValueType(),
                        'value' => $condition->getMaxValue(),
                    ];
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Равномерно распределяет скидку
     *
     * @param $discount
     *
     * @return int Абсолютный размер скидки (в руб.), который удалось использовать
     */
    protected function applyEvenly($discount, Collection $offerIds)
    {
        $priceOrders = $this->input->getPriceOrders();
        if ($priceOrders <= 0) {
            return 0.;
        }

        # Текущее значение скидки (в рублях, без учета скидок, которые могли применяться ранее)
        $currentDiscountValue = 0;
        # Номинальное значение скидки (в рублях)
        $discountValue = $discount->value_type === Discount::DISCOUNT_VALUE_TYPE_PERCENT
            ? round($priceOrders * $discount->value / 100)
            : $discount->value;
        # Скидка не может быть больше, чем стоимость всей корзины
        $discountValue = min($discountValue, $priceOrders);

        /**
         * Если скидку невозможно распределить равномерно по всем товарам,
         * то использовать скидку, сверх номинальной
         */
        $force = false;
        $prevCurrentDiscountValue = 0;
        while ($currentDiscountValue < $discountValue && $priceOrders !== 0) {
            /**
             * Сортирует ID офферов.
             * Сначала применяем скидки на самые дорогие товары (цена * количество)
             * Если необходимо использовать скидку сверх номинальной ($force), то сортируем в обратном порядке.
             */
            $offerIds = $this->sortOrderIdsByTotalPrice($offerIds, $force);
            $coefficient = ($discountValue - $currentDiscountValue) / $priceOrders;
            foreach ($offerIds as $offerId) {
                $offer = &$this->input->offers[$offerId];
                $valueUp = ceil($offer['price'] * $coefficient);
                $valueDown = floor($offer['price'] * $coefficient);
                $changeUp = $this->changePrice($offer, $valueUp, Discount::DISCOUNT_VALUE_TYPE_RUB, false);
                $changeDown = $this->changePrice($offer, $valueDown, Discount::DISCOUNT_VALUE_TYPE_RUB, false);
                if ($changeUp * $offer['qty'] <= $discountValue - $currentDiscountValue || $force) {
                    $change = $this->changePrice($offer, $valueUp, Discount::DISCOUNT_VALUE_TYPE_RUB);
                } elseif ($changeDown * $offer['qty'] <= $discountValue - $currentDiscountValue || $force) {
                    $change = $this->changePrice($offer, $valueDown, Discount::DISCOUNT_VALUE_TYPE_RUB);
                } else {
                    continue;
                }

                if (!$this->offersByDiscounts->has($offerId)) {
                    $this->offersByDiscounts->put($offerId, collect());
                }

                $this->offersByDiscounts[$offerId]->push([
                    'id' => $discount->id,
                    'change' => $change,
                    'value' => $discount->value,
                    'value_type' => $discount->value_type,
                ]);

                $currentDiscountValue += $change * $offer['qty'];
                if ($currentDiscountValue >= $discountValue) {
                    break 2;
                }
            }

            $priceOrders = $this->input->getPriceOrders();
            if ($prevCurrentDiscountValue === $currentDiscountValue) {
                if ($force) {
                    break;
                }

                $force = true;
            }

            $prevCurrentDiscountValue = $currentDiscountValue;
        }

        return $currentDiscountValue;
    }

    /**
     * Вместо равномерного распределения скидки по офферам (applyEvenly), применяет скидку к каждому офферу
     *
     * @param Discount $discount
     *
     * @return bool Результат применения скидки
     */
    protected function applyDiscountToOffer($discount, Collection $offerIds)
    {
        $offerIds = $offerIds->filter(function ($offerId) use ($discount) {
            return $this->applicableToOffer($discount, $offerId);
        });

        if ($offerIds->isEmpty()) {
            return false;
        }

        $value = $discount->value;
        $valueType = $discount->value_type;
        if ($discount->type == Discount::DISCOUNT_TYPE_BUNDLE_OFFER) {
            if ($valueType == Discount::DISCOUNT_VALUE_TYPE_RUB) {
                $value /= $offerIds->count();
            }
        }

        $changed = 0;
        foreach ($offerIds as $offerId) {
            $offer = &$this->input->offers[$offerId];
            $lowestPossiblePrice = $offer['product_id'] ? self::LOWEST_POSSIBLE_PRICE : self::LOWEST_MASTERCLASS_PRICE;

            // Если в условии на суммирование скидки было "не более x%", то переопределяем минимально возможную цену товара
            if (isset($this->maxValueByDiscount[$discount->id])) {
                // Получаем величину скидки, которая максимально возможна по условию
                $maxDiscountValue = $this->calculateDiscountByType(
                    $offer['cost'],
                    $this->maxValueByDiscount[$discount->id]['value'],
                    $this->maxValueByDiscount[$discount->id]['value_type']
                );

                // Чтобы не получить минимально возможную цену меньше 1р, выбираем наибольшее значение
                $lowestPossiblePrice = max($lowestPossiblePrice, $offer['cost'] - $maxDiscountValue);
            }

            $change = $this->changePrice($offer, $value, $valueType, true, $lowestPossiblePrice, $discount);

            if ($change <= 0) {
                continue;
            }

            if (!$this->offersByDiscounts->has($offerId)) {
                $this->offersByDiscounts->put($offerId, collect());
            }

            $this->offersByDiscounts[$offerId]->push([
                'id' => $discount->id,
                'change' => $change,
                'value' => $discount->value,
                'value_type' => $discount->value_type,
            ]);
            if ($discount->type == Discount::DISCOUNT_TYPE_BUNDLE_OFFER) {
                $qty = $offer['bundles'][$discount->id]['qty'];
            } else {
                $qty = $offer['qty'];
            }

            $changed += $change * $qty;
        }

        return $changed;
    }

    /**
     * @return Collection
     */
    public function getExternalDiscountFormat()
    {
        $discounts = $this->discounts->filter(function ($discount) {
            return $this->appliedDiscounts->has($discount->id);
        })->keyBy('id');

        $items = collect();
        foreach ($discounts as $discount) {
            $discountId = $discount->id;
            $conditions = $this->relations['conditions']->has($discountId)
                ? $this->relations['conditions'][$discountId]->toArray()
                : [];

            $extType = Discount::getExternalType($discount['type'], $conditions, $discount->promo_code_only);
            $isPromoCodeDiscount = $this->input->promoCodeDiscount && $this->input->promoCodeDiscount->id = $discountId;
            $items->push([
                'id' => $discountId,
                'name' => $discount->name,
                'type' => $discount->type,
                'external_type' => $extType,
                'change' => $this->appliedDiscounts[$discountId]['change'],
                'merchant_id' => $discount->merchant_id,
                'visible_in_catalog' => $extType === Discount::EXT_TYPE_OFFER,
                'promo_code_only' => $discount->promo_code_only,
                'promo_code' => $isPromoCodeDiscount ? $this->input->promoCode : null,
            ]);
        }

        return $items;
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
     * @param bool $asc
     *
     * @return Collection
     */
    protected function sortOrderIdsByTotalPrice(Collection $offerIds, $asc = true)
    {
        return $offerIds->sort(function ($offerIdLft, $offerIdRgt) use ($asc) {
            $offerLft = $this->input->offers[$offerIdLft];
            $totalPriceLft = $offerLft['price'] * $offerLft['qty'];
            $offerRgt = $this->input->offers[$offerIdRgt];
            $totalPriceRgt = $offerRgt['price'] * $offerRgt['qty'];

            return ($asc ? 1 : -1) * ($totalPriceLft - $totalPriceRgt);
        });
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
     * Загружает необходимые данные о полученных скидках ($this->discount)
     * @return $this
     * @throws PimException
     */
    protected function fetchRelations()
    {
        $this->fetchDiscountOffers()
            ->fetchDiscountBrands()
            ->fetchDiscountCategories()
            ->fetchDiscountPublicEvents()
            ->fetchDiscountSegments()
            ->fetchDiscountCustomerRoles()
            ->fetchBundleItems();

        return $this;
    }

    /**
     * Получаем все возможные скидки и офферы из DiscountOffer
     * @return $this
     */
    protected function fetchDiscountOffers()
    {
        $validTypes = [Discount::DISCOUNT_TYPE_OFFER, Discount::DISCOUNT_TYPE_BRAND, Discount::DISCOUNT_TYPE_CATEGORY];
        if ($this->input->offers->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['offers'] = collect();

            return $this;
        }

        $this->relations['offers'] = DiscountOffer::select(['discount_id', 'offer_id', 'except'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->whereIn('offer_id', $this->input->offers->pluck('id'))
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
        if ($this->input->brands->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['brands'] = collect();
            return $this;
        }

        $this->relations['brands'] = DiscountBrand::select(['discount_id', 'brand_id', 'except'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->whereIn('brand_id', $this->input->brands->keys())
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получаем все возможные скидки и категории из DiscountCategory
     * @return $this
     * @throws PimException
     */
    protected function fetchDiscountCategories()
    {
        /** Если не передали офферы, то пропускаем скидки на категорию */
        $validTypes = [Discount::DISCOUNT_TYPE_CATEGORY];
        if ($this->input->categories->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['categories'] = collect();

            return $this;
        }

        $categories = InputCalculator::getAllCategories();
        $this->relations['categories'] = DiscountCategory::select(['discount_id', 'category_id'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->get()
            ->filter(function ($discountCategory) use ($categories) {
                $categoryLeaf = $categories[$discountCategory->category_id];
                foreach ($this->input->categories->keys() as $categoryId) {
                    if ($categoryLeaf->isSelfOrAncestorOf($categories[$categoryId])) {
                        return true;
                    }
                }

                return false;
            })
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получаем скидки для мастер-классов
     * @uses \App\Models\Discount\DiscountPublicEvent
     * @return $this
     */
    protected function fetchDiscountPublicEvents()
    {
        /** Если не передали ID типов билетов, то пропускаем скидки на мастер-классы */
        $validTypes = [Discount::DISCOUNT_TYPE_MASTERCLASS, Discount::DISCOUNT_TYPE_ANY_MASTERCLASS];
        if ($this->input->ticketTypeIds->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['ticketTypeIds'] = collect();

            return $this;
        }

        $this->relations['ticketTypeIds'] = DiscountPublicEvent::select(['discount_id', 'ticket_type_id'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->whereIn('ticket_type_id', $this->input->ticketTypeIds->keys())
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
            ->whereIn('discount_id', $this->discounts->pluck('id'))
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
    protected function fetchDiscountCustomerRoles()
    {
        $this->relations['roles'] = DiscountUserRole::select(['discount_id', 'role_id'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->get()
            ->groupBy('discount_id')
            ->transform(function ($segments) {
                return $segments->pluck('role_id');
            });

        return $this;
    }

    /**
     * Получаем все возможные скидки из BundleItems
     * @return $this
     */
    protected function fetchBundleItems()
    {
        /** Если не передали офферы, то пропускаем скидки на бренды */
        $validTypes = [Discount::DISCOUNT_TYPE_BUNDLE_OFFER, Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS];
        if ($this->input->bundles->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['bundleItems'] = collect();
            return $this;
        }

        $this->relations['bundleItems'] = BundleItem::query()
            ->select(['discount_id', 'item_id'])
            ->whereIn('discount_id', $this->input->bundles)
            ->get()
            ->groupBy('discount_id');

        return $this;
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

    /**
     * Получить все активные скидки
     *
     * @return $this
     */
    protected function fetchActiveDiscounts()
    {
        $this->discounts = Discount::select([
            'id',
            'type',
            'name',
            'merchant_id',
            'value',
            'value_type',
            'promo_code_only',
            'merchant_id',
        ])
            ->where(function ($query) {
                $query->where('promo_code_only', false);
                $promoCodeDiscountId = $this->input->promoCodeDiscount->id ?? null;
                if ($promoCodeDiscountId) {
                    $query->orWhere('id', $promoCodeDiscountId);
                }
            })
            ->active()
            ->orderBy('promo_code_only')
            ->orderBy('type')
            ->get();

        return $this;
    }
}
