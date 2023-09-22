<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class OrderSequenceNumberConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        $countOrders = $this->input->getCustomerOrdersCount();
        return isset($countOrders) && ((($countOrders + 1) % $this->condition->getOrderSequenceNumber()) === 0);
    }
}
