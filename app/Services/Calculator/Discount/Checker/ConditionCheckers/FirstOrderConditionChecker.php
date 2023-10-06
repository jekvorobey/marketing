<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class FirstOrderConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        return $this->input->getCustomerOrdersCount() === 0;
    }
}
