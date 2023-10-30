<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class MinPriceOrderConditionChecker extends AbstractConditionChecker
{
    // при проверке условия с доставкой, проверяется по price, а не по cost
    public const USE_PRICE_PARAM = 'use_price';

    /**
     * @return bool
     */
    public function check(): bool
    {
        $amount = $this->getExtraParam(self::USE_PRICE_PARAM)
            ? $this->input->getPriceOrders()
            : $this->input->getCostOrders();

        return $amount >= $this->condition->getMinPrice();
    }
}
