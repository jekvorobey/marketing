<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\CalculatorChangePrice;
use App\Services\Calculator\InputCalculator;
use Illuminate\Support\Collection;

class OfferApplier implements Applier
{
    /**
     * Если по какой-то скидке есть ограничение на максимальный итоговый размер скидки по офферу
     * @var array [discount_id => ['value' => value, 'value_type' => value_type], ...]
     */
    private array $maxValueByDiscount = [];
    private InputCalculator $input;
    private Collection $offersByDiscounts;
    private Collection $appliedDiscounts;
    private Collection $offerIds;

    public function __construct(
        InputCalculator $input,
        Collection $offersByDiscounts,
        Collection $appliedDiscounts
    ) {
        $this->input = $input;
        $this->offersByDiscounts = $offersByDiscounts;
        $this->appliedDiscounts = $appliedDiscounts;
    }

    public function setOfferIds(Collection $offerIds): void
    {
        $this->offerIds = $offerIds;
    }

    public function getModifiedOffersByDiscounts(): Collection
    {
        return $this->offersByDiscounts;
    }

    public function getModifiedInputOffers(): Collection
    {
        return $this->input->offers;
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
                if (isset($changedPrice['discount'])) {
                    $offer['discount'] = $changedPrice['discount'];
                }
                if (isset($changedPrice['price'])) {
                    $offer['price'] = $changedPrice['price'];
                }
                if (isset($changedPrice['cost'])) {
                    $offer['cost'] = $changedPrice['cost'];
                }
                $change = $changedPrice['discountValue'];
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
        }

        return $changed;
    }

    /**
     * Можно ли применить скидку к офферу
     */
    protected function applicableToOffer(Discount $discount, $offerId): bool
    {
        if (
            $discount->type === Discount::DISCOUNT_TYPE_BUNDLE_OFFER ||
            $discount->type === Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS ||
            $discount->type === Discount::DISCOUNT_TYPE_ANY_BUNDLE
        ) {
            return true;
        }

        if ($this->appliedDiscounts->isEmpty() || !$this->offersByDiscounts->has($offerId)) {
            return true;
        }

        /** @var Collection $discountIdsForOffer */
        $discountIdsForOffer = $this->offersByDiscounts[$offerId]->pluck('id');

        $discountConditions = $discount->conditions;
        /** @var DiscountCondition $condition */
        foreach ($discountConditions as $condition) {
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
}
