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
        if ($discount->type === Discount::DISCOUNT_TYPE_BUNDLE_OFFER && $valueType === Discount::DISCOUNT_VALUE_TYPE_RUB) {
            $basketItems = $basketItems->where('bundle_id', $discount->id);
            $value /= $basketItems->count();
        }

        $hasProductQtyLimit = $discount->product_qty_limit > 0;
        $restProductQtyLimit = $discount->product_qty_limit;
        $changed = 0;
        foreach ($basketItems as $basketItemId => $basketItem) {
            if (isset($this->input->basketItems[$basketItemId])) {
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
                    $changedPrice = $this->getChangedPriceForLastBundleItem($discount, $changedPrice, $changed);
                }

                $basketItem = $calculatorChangePrice->syncItemWithChangedPrice($basketItem, $changedPrice);

                if ($discount->type === Discount::DISCOUNT_TYPE_BUNDLE_OFFER && isset($changedPrice['bundles'][$discount->id])) {
                    $basketItem['bundles'][$discount->id] = $changedPrice['bundles'][$discount->id];
                }

                $change = $changedPrice['discountValue'];
                if ($change <= 0) {
                    continue;
                }

                $this->addBasketItemByDiscount($basketItemId, $discount, $change);

                if ($discount->type == Discount::DISCOUNT_TYPE_BUNDLE_OFFER) {
                    $qty = $basketItem['bundles'][$discount->id]['qty'];
                } else {
                    $qty = $basketItem['qty'];
                }

                $changed += $change * $qty;
            }
        }

        return $changed;
    }

    private function getChangedPriceForLastBundleItem(Discount $discount, array $changedPrice, int $changed): array
    {
        $restOfDiscount = max(0, $discount->value - $changed);
        $diffOfDiscount = max(0, $restOfDiscount - $changedPrice['discountValue']);
        if (
            $discount->type === Discount::DISCOUNT_TYPE_BUNDLE_OFFER
            && isset($changedPrice['bundles'][$discount->id])
            && $discount->value_type === Discount::DISCOUNT_VALUE_TYPE_RUB
            && $diffOfDiscount > 0
        ) {
            $changedPrice['bundles'][$discount->id]['price'] -= $diffOfDiscount;
            $changedPrice['bundles'][$discount->id]['cost'] -= $diffOfDiscount;
            $changedPrice['bundles'][$discount->id]['discount'] += $diffOfDiscount;
            $changedPrice['discountValue'] = max(0, $restOfDiscount);
        }

        return $changedPrice;
    }
}
