<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class CustomerConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        return in_array($this->input->getCustomerId(), $this->condition->getCustomerIds());
    }
}
