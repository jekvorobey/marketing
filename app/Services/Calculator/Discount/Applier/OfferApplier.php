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
        $basketItems = $this->input->basketItems
            ->whereIn('offer_id', $this->offerIds->toArray())
            ->filter(function ($basketItem) use ($discount) {
                return $this->applicableToBasketItem($discount, $basketItem['id']);
            });

        if ($basketItems->isEmpty()) {
            return null;
        }

        $calculatorChangePrice = new CalculatorChangePrice();
        $value = $discount->value;
        $valueType = $discount->value_type;
        if ($discount->type === Discount::DISCOUNT_TYPE_BUNDLE_OFFER) {
            $basketItems = $basketItems->where('bundle_id', $discount->id);

            if ($valueType === Discount::DISCOUNT_VALUE_TYPE_RUB) {
                $value /= $basketItems->count();
            }
        }

        $hasProductQtyLimit = $discount->product_qty_limit > 0;
        $restProductQtyLimit = $discount->product_qty_limit;
        $changed = 0;
        foreach ($basketItems as $basketItemId => $basketItem) {
            $lowestPossiblePrice = $basketItem['product_id']
                ? CalculatorChangePrice::LOWEST_POSSIBLE_PRICE
                : CalculatorChangePrice::LOWEST_MASTERCLASS_PRICE;

            // Если в условии на суммирование скидки было "не более x%", то переопределяем минимально возможную цену товара
            if (isset($this->maxValueByDiscount[$discount->id])) {
                // Получаем величину скидки, которая максимально возможна по условию
                $maxDiscountValue = $calculatorChangePrice->calculateDiscountByType(
                    $basketItem['cost'],
                    $this->maxValueByDiscount[$discount->id]['value'],
                    $this->maxValueByDiscount[$discount->id]['value_type']
                );

                // Чтобы не получить минимально возможную цену меньше 1р, выбираем наибольшее значение
                $lowestPossiblePrice = max($lowestPossiblePrice, $basketItem['cost'] - $maxDiscountValue);
            }

            if ($hasProductQtyLimit) {
                if ($restProductQtyLimit <= 0) {
                    break;
                }

                $valueType = $discount->value_type;
                $maxDiscountValue = $calculatorChangePrice->calculateDiscountByType($basketItem['price'], $value, $valueType);
                $valueOfLimitDiscount = ceil($maxDiscountValue * min($basketItem['qty'], $restProductQtyLimit) / $basketItem['qty']);
                $valueType = Discount::DISCOUNT_VALUE_TYPE_RUB;
                $restProductQtyLimit -= $basketItem['qty'];
            }

            $changedPrice = $calculatorChangePrice->changePrice($basketItem, $valueOfLimitDiscount ?? $value, $valueType, $lowestPossiblePrice, $discount);

            if ($basketItems->keys()->last() === $basketItemId) {
                $changedPrice = $this->getChangedPriceForLastBundleItem($discount, $basketItem['bundle_id'], $changedPrice, $changed);
            }

            $basketItem = $calculatorChangePrice->syncItemWithChangedPrice($basketItem, $changedPrice);

            $change = $changedPrice['discountValue'];
            if ($change <= 0) {
                continue;
            }

            $this->addBasketItemByDiscount($basketItemId, $discount, $change);
            $qty = $basketItem['qty'];

            $changed += $change * $qty;
        }

        return $changed;
    }

    private function getChangedPriceForLastBundleItem(
        Discount $discount,
        int $bundleId,
        array $changedPrice,
        int $changed
    ): array {
        if (
            $discount->type === Discount::DISCOUNT_TYPE_BUNDLE_OFFER
            && $bundleId === $discount->id
        ) {
            $restOfDiscount = 0;
            switch ($discount->value_type) {
                case Discount::DISCOUNT_VALUE_TYPE_RUB:
                    $restOfDiscount = max(0, $discount->value - $changed);
                    break;
                case Discount::DISCOUNT_VALUE_TYPE_PERCENT:
                    $fullDiscount = CalculatorChangePrice::percent($this->input->basketItems->sum('cost') + $changedPrice['cost'], $discount->value);
                    $restOfDiscount = max(0, $fullDiscount - $changed);
                    break;
            }

            $diffOfDiscount = max(0, $restOfDiscount - $changedPrice['discountValue']);

            if ($diffOfDiscount > 0) {
                $changedPrice['price'] -= $diffOfDiscount;
                $changedPrice['discount'] += $diffOfDiscount;
                $changedPrice['discountValue'] = max(0, $restOfDiscount);
            }
        }

        return $changedPrice;
    }
}
