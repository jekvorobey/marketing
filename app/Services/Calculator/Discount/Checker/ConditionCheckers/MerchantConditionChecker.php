<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class MerchantConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        $success = $this->input
            ->basketItems
            ->pluck('merchant_id')
            ->intersect($this->condition->getMerchants())
            ->isNotEmpty();

        if ($success) {
            $this->saveConditionToStore();
        }

        return $success;
    }
}
