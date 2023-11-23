<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class MinPriceOrderConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        return $this->input->getOrderPrice() >= $this->condition->getMinPrice();
    }
}
