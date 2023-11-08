<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class CustomerConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        $conditionCustomers = $this->condition->getCustomerIds();

        return empty($conditionCustomers) || in_array($this->input->getCustomerId(), $conditionCustomers);
    }
}
