<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Services\Calculator\CalculatorChangePrice;

class DeliveryApplier implements Applier
{
    private $currentDeliveries;

    public function setCurrentDeliveries(&$currentDeliveries): void
    {
        $this->currentDeliveries = &$currentDeliveries;
    }

    public function apply(Discount $discount): ?float
    {
        $calculatorChangePrice = new CalculatorChangePrice();

        return $calculatorChangePrice->changePrice(
            $this->currentDeliveries,
            $discount->value,
            $discount->value_type,
            true,
            CalculatorChangePrice::FREE_DELIVERY_PRICE
        );
    }
}
