<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Services\Calculator\CalculatorChangePrice;
use Illuminate\Support\Collection;

class OfferApplier extends AbstractApplier
{
    private Collection $offerIds;

    public function setOfferIds(Collection $offerIds): void
    {
        $this->offerIds = $offerIds;
    }

    public function apply(Discount $discount): ?float
    {
        $offerIds = $this->offerIds->filter(function ($offerId) use ($discount) {
            return $this->applicableToOffer($discount, $offerId);
        });

        if ($offerIds->isEmpty()) {
            return null;
        }

        $calculatorChangePrice = new CalculatorChangePrice();
        $value = $discount->value;
        $valueType = $discount->value_type;
        if ($discount->type === Discount::DISCOUNT_TYPE_BUNDLE_OFFER && $valueType === Discount::DISCOUNT_VALUE_TYPE_RUB) {
            $value /= $offerIds->count();
        }

        $hasProductQtyLimit = $discount->product_qty_limit > 0;
        $restProductQtyLimit = $discount->product_qty_limit;
        $changed = 0;
        foreach ($offerIds as $offerId) {
            if (isset($this->input->offers[$offerId])) {
                $offer = $this->input->offers[$offerId];
                $lowestPossiblePrice = $offer['product_id'] ? CalculatorChangePrice::LOWEST_POSSIBLE_PRICE : CalculatorChangePrice::LOWEST_MASTERCLASS_PRICE;

                // Если в условии на суммирование скидки было "не более x%", то переопределяем минимально возможную цену товара
                if (isset($this->maxValueByDiscount[$discount->id])) {
                    // Получаем величину скидки, которая максимально возможна по условию
                    $maxDiscountValue = $calculatorChangePrice->calculateDiscountByType(
                        $offer['cost'],
                        $this->maxValueByDiscount[$discount->id]['value'],
                        $this->maxValueByDiscount[$discount->id]['value_type']
                    );

                    // Чтобы не получить минимально возможную цену меньше 1р, выбираем наибольшее значение
                    $lowestPossiblePrice = max($lowestPossiblePrice, $offer['cost'] - $maxDiscountValue);
                }

                if ($hasProductQtyLimit) {
                    if ($restProductQtyLimit <= 0) {
                        break;
                    }

                    $valueType = $discount->value_type;
                    $maxDiscountValue = $calculatorChangePrice->calculateDiscountByType($offer['price'], $value, $valueType);
                    $valueOfLimitDiscount = ceil($maxDiscountValue * min($offer['qty'], $restProductQtyLimit) / $offer['qty']);
                    $valueType = Discount::DISCOUNT_VALUE_TYPE_RUB;
                    $restProductQtyLimit -= $offer['qty'];
                }

                $changedPrice = $calculatorChangePrice->changePrice($offer, $valueOfLimitDiscount ?? $value, $valueType, $lowestPossiblePrice, $discount);
                $offer = $calculatorChangePrice->syncItemWithChangedPrice($offer, $changedPrice);

                if ($discount->type === Discount::DISCOUNT_TYPE_BUNDLE_OFFER && isset($changedPrice['bundles'][$discount->id])) {
                    $offer['bundles'][$discount->id] = $changedPrice['bundles'][$discount->id];
                }

                $change = $changedPrice['discountValue'];
                if ($change <= 0) {
                    continue;
                }

                $this->addOfferByDiscount($offerId, $discount, $change);

                if ($discount->type == Discount::DISCOUNT_TYPE_BUNDLE_OFFER) {
                    $qty = $offer['bundles'][$discount->id]['qty'];
                } else {
                    $qty = $offer['qty'];
                }

                $changed += $change * $qty;
            }
        }

        return $changed;
    }
}
