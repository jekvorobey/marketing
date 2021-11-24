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

    public function getModifiedCurrentDeliveries()
    {
        return $this->currentDeliveries;
    }

    public function apply(Discount $discount): ?float
    {
        $calculatorChangePrice = new CalculatorChangePrice();

        $changedPrice = $calculatorChangePrice->changePrice(
            $this->currentDeliveries,
            $discount->value,
            $discount->value_type,
            CalculatorChangePrice::FREE_DELIVERY_PRICE
        );
        $this->currentDeliveries['discount'] = $changedPrice['discount'];
        $this->currentDeliveries['price'] = $changedPrice['price'];
        $this->currentDeliveries['cost'] = $changedPrice['cost'];

        return $changedPrice['discountValue'];
    }
}
