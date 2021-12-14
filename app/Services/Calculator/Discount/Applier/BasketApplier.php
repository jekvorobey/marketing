<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Services\Calculator\CalculatorChangePrice;
use Illuminate\Support\Collection;

class BasketApplier extends AbstractApplier
{
    /**
     * @return false|float|int
     */
    public function apply(Discount $discount): ?float
    {
        $offerIds = $this->input->offers->filter(function ($offer) use ($discount) {
            return $this->applicableToOffer($discount, $offer['id']);
        })->pluck('id');

        if ($offerIds->isEmpty()) {
            return null;
        }

        return $this->applyEvenly($discount, $offerIds);
    }

    /**
     * Равномерно распределяет скидку
     */
    protected function applyEvenly(Discount $discount, Collection $offerIds): ?float
    {
        $calculatorChangePrice = new CalculatorChangePrice();
        $priceOrders = $this->getBasketPriceOrders($offerIds);
        if ($priceOrders <= 0) {
            return 0.;
        }

        # Текущее значение скидки (в рублях, без учета скидок, которые могли применяться ранее)
        $currentDiscountValue = 0;
        # Номинальное значение скидки (в рублях)
        $discountValue = $calculatorChangePrice->calculateDiscountByType($priceOrders, $discount->value, $discount->value_type);
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
                $changeUp = $calculatorChangePrice->changePrice($offer, $valueUp)['discountValue'];
                $changeDown = $calculatorChangePrice->changePrice($offer, $valueDown)['discountValue'];
                if ($changeUp * $offer['qty'] <= $discountValue - $currentDiscountValue || $force) {
                    $change = $calculatorChangePrice->changePrice($offer, $valueUp)['discountValue'];
                } elseif ($changeDown * $offer['qty'] <= $discountValue - $currentDiscountValue || $force) {
                    $change = $calculatorChangePrice->changePrice($offer, $valueDown)['discountValue'];
                } else {
                    continue;
                }

                $this->addOfferByDiscount($offerId, $discount, $change);

                $currentDiscountValue += $change * $offer['qty'];
                if ($currentDiscountValue >= $discountValue) {
                    break 2;
                }
            }

            $priceOrders = $this->getBasketPriceOrders($offerIds);
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

    private function sortOrderIdsByTotalPrice(Collection $offerIds, bool $asc = true): Collection
    {
        return $offerIds->sort(function ($offerIdLft, $offerIdRgt) use ($asc) {
            $offerLft = $this->input->offers[$offerIdLft];
            $totalPriceLft = $offerLft['price'] * $offerLft['qty'];
            $offerRgt = $this->input->offers[$offerIdRgt];
            $totalPriceRgt = $offerRgt['price'] * $offerRgt['qty'];

            return ($asc ? 1 : -1) * ($totalPriceLft - $totalPriceRgt);
        });
    }

    protected function getBasketPriceOrders(Collection $offerIds): float
    {
        return $this->input->offers->whereIn('id', $offerIds)->sum(fn($offer) => $offer['price'] * $offer['qty']);
    }
}
