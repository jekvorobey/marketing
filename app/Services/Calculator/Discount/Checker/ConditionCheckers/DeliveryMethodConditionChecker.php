<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class DeliveryMethodConditionChecker extends AbstractConditionChecker
{
    public const DELIVERY_METHOD_PARAM = 'delivery_method';

    /**
     * @return bool
     */
    public function check(): bool
    {
        $deliveryMethod = $this->getExtraParam(self::DELIVERY_METHOD_PARAM);

        if (is_null($deliveryMethod)) {
            return false;
        }

        return in_array(
            $deliveryMethod,
            $this->condition->getDeliveryMethods()
        );
    }
}
