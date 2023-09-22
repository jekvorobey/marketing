<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class MinPriceBrandConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        return $this->input->getMaxTotalPriceForBrands($this->condition->getBrands()) >= $this->condition->getMinPrice();
    }
}
