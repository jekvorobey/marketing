<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class MinPriceCategoryConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        return $this->input->getMaxTotalPriceForCategories($this->condition->getCategories()) >= $this->condition->getMinPrice();
    }
}
