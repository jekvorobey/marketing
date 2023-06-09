<?php

namespace App\Services\Calculator\Discount\Checker;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;

class DifferentProductsCountChecker extends BaseDiscountConditionChecker
{
    public function check(Discount $discount, array $checkingConditionTypes = []): bool
    {
        //ищем все условия скидки с типом DiscountCondition::DIFFERENT_PRODUCTS_COUNT (их может быть несколько в одной скидке)
        $conditions = $discount->conditions
            ->filter(fn($condition) => $condition->type == DiscountCondition::DIFFERENT_PRODUCTS_COUNT && $condition->getCount() !== null)
            ->sortByDesc(fn($condition) => $condition->getCount());

        if ($conditions->isEmpty()) {
            return true;
        }

        $differentProductsCount = $this->input->basketItems->groupBy('product_id')->count();
        $relevantCondition = $this->getRelevantCondition($discount, $differentProductsCount);

        if ($relevantCondition) {
            if (!$discount->relevantConditionsWithAdditionalDiscount->has(spl_object_hash($relevantCondition))) {
                $discount->relevantConditionsWithAdditionalDiscount->put(spl_object_hash($relevantCondition), $relevantCondition);
            }
            return true;
        }

        return false;
    }

    protected function getRelevantCondition(Discount $discount, int $differentProductsCount): ?DiscountCondition
    {
        $relevantCondition = $discount->conditions
            ->filter(fn($condition) => $condition->type == DiscountCondition::DIFFERENT_PRODUCTS_COUNT && $condition->getCount() !== null)
            ->sortByDesc(fn($condition) => $condition->getCount())
            ->filter(fn($condition) => $differentProductsCount >= $condition->getCount())
            ->first();

        return $relevantCondition;
    }
}
