<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\CalculatorChangePrice;
use App\Services\Calculator\Discount\Checker\DifferentProductsCountChecker;
use Illuminate\Support\Collection;

class BasketApplier extends AbstractApplier
{
    /**
     * @return false|float|int
     */
    public function apply(Discount $discount, bool $justCalculate = false): ?float
    {
        $basketItemIds = $this->input->basketItems->filter(function ($basketItem) use ($discount) {
            return $this->applicableToBasketItem($discount, $basketItem);
        })->pluck('id');

        if ($basketItemIds->isEmpty()) {
            return null;
        }

        return $this->applyEvenly($discount, $basketItemIds, $justCalculate);
    }

    /**
     * Равномерно распределяет скидку
     */
    protected function applyEvenly(Discount $discount, Collection $basketItemIds, bool $justCalculate = false): ?float
    {
        $calculatorChangePrice = new CalculatorChangePrice();
        $priceOrders = $this->getBasketPriceOrders($basketItemIds);
        if ($priceOrders <= 0) {
            return 0.;
        }

        # Текущее значение скидки (в рублях, без учета скидок, которые могли применяться ранее)
        $currentDiscountValue = 0;
        # Номинальное значение скидки (в рублях)
        $discountValue = $calculatorChangePrice->calculateDiscountByType($priceOrders, $this->getDiscountValue($discount), $discount->value_type);
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
            $basketItemIds = $this->sortOrderIdsByTotalPrice($basketItemIds, $force);
            $coefficient = ($discountValue - $currentDiscountValue) / $priceOrders;
            foreach ($basketItemIds as $basketItemId) {
                $basketItem = &$this->input->basketItems[$basketItemId];
                $valueUp = ceil($basketItem['price'] * $coefficient);
                $valueDown = floor($basketItem['price'] * $coefficient);
                $changeUp = $calculatorChangePrice->changePrice($basketItem, $valueUp)['discountValue'];
                $changeDown = $calculatorChangePrice->changePrice($basketItem, $valueDown)['discountValue'];
                if ($changeUp * $basketItem['qty'] <= $discountValue - $currentDiscountValue || $force) {
                    $changedPrice = $calculatorChangePrice->changePrice($basketItem, $valueUp);
                    $change = $changedPrice['discountValue'];
                    if(!$justCalculate) {
                        $basketItem = $calculatorChangePrice->syncItemWithChangedPrice($basketItem, $changedPrice);
                    }
                } elseif ($changeDown * $basketItem['qty'] <= $discountValue - $currentDiscountValue || $force) {
                    $changedPrice = $calculatorChangePrice->changePrice($basketItem, $valueDown);
                    $change = $changedPrice['discountValue'];
                    if(!$justCalculate) {
                        $basketItem = $calculatorChangePrice->syncItemWithChangedPrice($basketItem, $changedPrice);
                    }
                } else {
                    continue;
                }

                if(!$justCalculate) {
                    $this->addBasketItemByDiscount($basketItemId, $discount, $change);
                }

                $currentDiscountValue += $change * $basketItem['qty'];
                if ($currentDiscountValue >= $discountValue) {
                    break 2;
                }
            }

            $priceOrders = $this->getBasketPriceOrders($basketItemIds);
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

    private function sortOrderIdsByTotalPrice(Collection $basketItemIds, bool $asc = true): Collection
    {
        return $basketItemIds->sort(function ($basketItemIdLft, $basketItemIdRgt) use ($asc) {
            $basketItemLft = $this->input->basketItems[$basketItemIdLft];
            $totalPriceLft = $basketItemLft['price'] * $basketItemLft['qty'];
            $basketItemRgt = $this->input->basketItems[$basketItemIdRgt];
            $totalPriceRgt = $basketItemRgt['price'] * $basketItemRgt['qty'];

            return ($asc ? 1 : -1) * ($totalPriceLft - $totalPriceRgt);
        });
    }

    protected function getBasketPriceOrders(Collection $basketItemIds): float
    {
        return $this->input->basketItems->whereIn('id', $basketItemIds)->sum(fn($basketItem) => $basketItem['price'] * $basketItem['qty']);
    }

    protected function getDiscountValue(Discount $discount): float
    {
        $discountValue = $discount->value;

        //применяем дополнительные скидки из условий
        foreach ($discount->relevantConditionsWithAdditionalDiscount as $condition) {
            $discountValue += $condition->getAdditionalDiscount();
        }

        return $discountValue;
    }
}
