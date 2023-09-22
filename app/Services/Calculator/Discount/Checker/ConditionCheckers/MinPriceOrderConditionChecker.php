<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class MinPriceOrderConditionChecker extends AbstractConditionChecker
{
    // при проверке условия с доставкой, проверяется по price, а не по cost
    public const KEY_USE_PRICE = 'use_price';

    /**
     * @return bool
     */
    public function check(): bool
    {
        $sum = $this->getExtraParam(self::KEY_USE_PRICE)
            ? $this->input->getPriceOrders()
            : $this->input->getCostOrders();

        return $sum >= $this->condition->getMinPrice();
    }
}
