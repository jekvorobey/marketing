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

        $isApplicableDiscount = !$this->input->offers->contains(function ($offer) use ($discount) {
            return !$this->applicableToOffer($discount, $offer['id']);
        });

        if (!$isApplicableDiscount) {
            return 0;
        }

        $changedPrice = $calculatorChangePrice->changePrice(
            $this->currentDelivery,
            $discount->value,
            $discount->value_type,
            CalculatorChangePrice::FREE_DELIVERY_PRICE
        );
        $this->currentDelivery = $calculatorChangePrice->syncItemWithChangedPrice($this->currentDelivery, $changedPrice);

        return $changedPrice['discountValue'];
    }
}
