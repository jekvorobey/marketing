<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class DeliveryMethodConditionChecker extends AbstractConditionChecker
{
    public const KEY_DELIVERY_METHOD = 'delivery_method';

    /**
     * @return bool
     */
    public function check(): bool
    {
        return in_array(
            $this->getExtraParam(self::KEY_DELIVERY_METHOD),
            $this->condition->getDeliveryMethods()
        );
    }
}
