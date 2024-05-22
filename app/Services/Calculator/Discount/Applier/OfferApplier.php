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
        $totalChange = 0;

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
                $maxDiscountValue = $calculatorChangePrice->calculateDiscountByType(
                    $basketItem['price'] ?? $basketItem['price_base'],
                    $value,
                    $valueType
                );
                $valueOfLimitDiscount = round(
                    $maxDiscountValue * min($basketItem['qty'], $restProductQtyLimit) / $basketItem['qty'],
                    4
                );
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
                /* для скидок по промокоду возможность применения проверяется здесь,
                   так как нужно проверять все скидки для каждого элемента корзины (нужен рефакторинг в будущем) */
                if ($discount->promo_code_only) {
                    /** @var Collection|null $basketItemDiscounts */
                    $basketItemDiscounts = $this->basketItemsByDiscounts->get($basketItemId);

                    $appliedPromocodeDiscounts = $this->getAppliedPromocodeDiscounts();

                    // примененные скидки, с которыми будет конкурировать скидка
                    $targetDiscounts = $discount->max_priority
                        ? $appliedPromocodeDiscounts->where('max_priority', true)
                        : $appliedPromocodeDiscounts->where('max_priority', false);
                    $targetDiscountIds = $targetDiscounts->pluck('id')->values();

                    // максимальная примененная к элементу скидка
                    $maxPromocodeDiscount = $basketItemDiscounts
                        ?->whereIn('id', $targetDiscountIds)
                        ->sortByDesc('change')
                        ->first();

                    $maxChange = $maxPromocodeDiscount['change'] ?? 0;

                    if (!$discount->isSynergyWithDiscounts($targetDiscountIds)) {
                        if ($change <= $maxChange) {
                            continue;
                        }

                        if (!$discount->max_priority) {
                            $maxPriorityDiscounts = $appliedPromocodeDiscounts
                                ->where('max_priority', true)
                                ->pluck('id');

                            if ($basketItemDiscounts?->whereIn('id', $maxPriorityDiscounts)->isNotEmpty()) {
                                continue;
                            }
                        }

                        if ($basketItemDiscounts && $maxPromocodeDiscount) {
                            // замена скидки на более выгодную
                            $this->basketItemsByDiscounts->put(
                                $basketItemId,
                                $basketItemDiscounts->where('id', '!=', $maxPromocodeDiscount['id'])
                            );

                            $basketItem['price'] += $maxChange;
                            $basketItem['discount'] -= $maxChange;
                            $this->input->basketItems->put($basketItemId, $basketItem);

                            $totalChange -= $maxChange;

                            $changedPrice = $calculatorChangePrice->changePrice(
                                $basketItem,
                                $valueOfLimitDiscount ?? $value,
                                $valueType,
                                $lowestPossiblePrice,
                                $discount
                            );
                            $change = $changedPrice['discountValue'];
                        }
                    }
                }

                $basketItem = $calculatorChangePrice->syncItemWithChangedPrice($basketItem, $changedPrice);
                $this->addBasketItemByDiscount($basketItemId, $discount, $change);

            }

            if ($change > 0) {
                $totalChange += $change * $basketItem['qty'];
            }
        }

        return $totalChange;
    }

    /**
     * Примененные скидки промокода, которые что-то изменили
     * @return Collection
     */
    protected function getAppliedPromocodeDiscounts(): Collection
    {
        return $this->input->promoCodeDiscounts
            ->whereIn('id', $this->appliedDiscounts->where('change', '>', 0)->keys());
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
