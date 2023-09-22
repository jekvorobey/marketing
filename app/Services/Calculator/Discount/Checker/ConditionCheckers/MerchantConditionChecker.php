<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

use App\Services\Calculator\Discount\DiscountConditionStore;

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
            $this->saveMerchantList();
        }

        return $success;
    }

    /**
     * Сохраняем в стор, чтобы потом проверять при применении к каждому basketItem
     * @return void
     */
    private function saveMerchantList(): void
    {
        info('saveMerchantList', [$this->condition->id]);
        DiscountConditionStore::put(spl_object_hash($this->condition), $this->condition);
    }
}
