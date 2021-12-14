<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Services\Calculator\CalculatorChangePrice;

class DeliveryApplier extends AbstractApplier
{
    private array $currentDelivery;

    public function setCurrentDelivery($currentDelivery): void
    {
        $this->currentDelivery = $currentDelivery;
    }

    public function getModifiedCurrentDelivery(): array
    {
        return $this->currentDelivery;
    }

    public function apply(Discount $discount): ?float
    {
        $calculatorChangePrice = new CalculatorChangePrice();

        $changedPrice = $calculatorChangePrice->changePrice(
            $this->currentDelivery,
            $discount->value,
            $discount->value_type,
            CalculatorChangePrice::FREE_DELIVERY_PRICE
        );
        $this->currentDelivery['discount'] = $changedPrice['discount'];
        $this->currentDelivery['price'] = $changedPrice['price'];
        $this->currentDelivery['cost'] = $changedPrice['cost'];

        return $changedPrice['discountValue'];
    }
}
