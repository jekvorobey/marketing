<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Services\Calculator\CalculatorChangePrice;
use Illuminate\Support\Collection;

class OfferApplier extends AbstractApplier
{
    private Collection $offerIds;

    /**
     * @param Collection $offerIds
     * @return void
     */
    public function setOfferIds(Collection $offerIds): void
    {
        $this->offerIds = $offerIds;
    }

    /**
     * @param Discount $discount
     * @param bool $justCalculate
     * @return float|null
     */
    public function apply(Discount $discount, bool $justCalculate = false): ?float
    {
        $basketItems = $this->input
            ->basketItems
            ->whereIn('offer_id', $this->offerIds->toArray())
            ->where('qty', '>', 0)     //иногда приходят запросы с qty=0 в корзине
            ->filter(function ($basketItem) use ($discount) {
                return $this->applicableToBasketItem($discount, $basketItem);
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

            // Если в условии на суммирование скидки было "не более x%",
            // то переопределяем минимально возможную цену товара
            if (isset($this->maxValueByDiscount[$discount->id])) {
                $cost = $basketItem['cost'] ?? $basketItem['price'];
                // Получаем величину скидки, которая максимально возможна по условию
                $maxDiscountValue = $calculatorChangePrice->calculateDiscountByType(
                    $cost,
                    $this->maxValueByDiscount[$discount->id]['value'],
                    $this->maxValueByDiscount[$discount->id]['value_type']
                );

                // Чтобы не получить минимально возможную цену меньше 1р, выбираем наибольшее значение
                $lowestPossiblePrice = max($lowestPossiblePrice, $cost - $maxDiscountValue);
            }

            if ($hasProductQtyLimit) {
                if ($restProductQtyLimit <= 0) {
                    break;
                }

                $valueType = $discount->value_type;
                $maxDiscountValue = $calculatorChangePrice->calculateDiscountByType($basketItem['price'], $value, $valueType);
                $valueOfLimitDiscount = round($maxDiscountValue * min($basketItem['qty'], $restProductQtyLimit) / $basketItem['qty'], 4);
                $valueType = Discount::DISCOUNT_VALUE_TYPE_RUB;
                $restProductQtyLimit -= $basketItem['qty'];
            }

            $changedPrice = $calculatorChangePrice->changePrice(
                $basketItem,
                    $valueOfLimitDiscount ?? $value,
                $valueType,
                $lowestPossiblePrice,
                $discount
            );

            /**
             * @todo Этот код ломает корзину с бандлами, не понятно для чего он. Закомментил.
             * @todo Нужно протестить на всех вариантах бандлов без этого кода
             */
//            if ($basketItems->keys()->last() === $basketItemId) {
//                $changedPrice = $this->getChangedPriceForLastBundleItem($discount, $basketItem['bundle_id'], $changedPrice, $changed);
//            }

            $change = $changedPrice['discountValue'];

            if (!$justCalculate) {
                $basketItem = $calculatorChangePrice->syncItemWithChangedPrice($basketItem, $changedPrice);

                $this->addBasketItemByDiscount($basketItemId, $discount, $change);
            }

            if ($change <= 0) {
                continue;
            }

            $changed += $change * $basketItem['qty'];
        }

        return $changed;
    }

    /**
     * @param Discount $discount
     * @param int $bundleId
     * @param array $changedPrice
     * @param float $changed
     * @return array
     */
    private function getChangedPriceForLastBundleItem(
        Discount $discount,
        int $bundleId,
        array $changedPrice,
        float $changed
    ): array {
        if (
            $discount->type === Discount::DISCOUNT_TYPE_BUNDLE_OFFER
            && $bundleId === $discount->id
        ) {
            $calculatorChangePrice = new CalculatorChangePrice();
            $fullCost = $this->input->basketItems->sum('cost') + $changedPrice['cost']; // ??
            $fullDiscount = $calculatorChangePrice->calculateDiscountByType($fullCost, $discount->value, $discount->value_type);
            $restOfDiscount = max(0, $fullDiscount - $changed);

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
