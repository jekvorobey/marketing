<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class PayMethodConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        $methods = $this->condition->getPaymentMethods();
        return isset($this->input->payment['method']) && in_array($this->input->payment['method'], $methods);
    }
}
