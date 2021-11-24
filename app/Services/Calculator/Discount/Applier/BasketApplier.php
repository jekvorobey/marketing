<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Services\Calculator\CalculatorChangePrice;
use App\Services\Calculator\InputCalculator;
use Illuminate\Support\Collection;

class BasketApplier implements Applier
{
    private InputCalculator $input;
    private Collection $offersByDiscounts;

    public function __construct(InputCalculator $input, Collection $offersByDiscounts)
    {
        $this->input = $input;
        $this->offersByDiscounts = $offersByDiscounts;
    }

    /**
     * @return false|float|int
     */
    public function apply(Discount $discount): ?float
    {
        if ($this->input->offers->isEmpty()) {
            return null;
        }

        return $this->applyEvenly($discount, $this->input->offers->pluck('id'));
    }

    /**
     * Равномерно распределяет скидку
     */
    protected function applyEvenly(Discount $discount, Collection $offerIds): ?float
    {
        $calculatorChangePrice = new CalculatorChangePrice();
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
                $changeUp = $calculatorChangePrice->changePrice($offer, $valueUp)['discountValue'];
                $changeDown = $calculatorChangePrice->changePrice($offer, $valueDown)['discountValue'];
                if ($changeUp * $offer['qty'] <= $discountValue - $currentDiscountValue || $force) {
                    $change = $calculatorChangePrice->changePrice($offer, $valueUp)['discountValue'];
                } elseif ($changeDown * $offer['qty'] <= $discountValue - $currentDiscountValue || $force) {
                    $change = $calculatorChangePrice->changePrice($offer, $valueDown)['discountValue'];
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
}
