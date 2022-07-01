<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountCondition as DiscountConditionModel;
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
        if (!$this->isApplicable($discount)) {
            return 0;
        }

        $calculatorChangePrice = new CalculatorChangePrice();

        $changedPrice = $calculatorChangePrice->changePrice(
            $this->currentDelivery,
            $discount->value,
            $discount->value_type,
            CalculatorChangePrice::FREE_DELIVERY_PRICE
        );
        $this->currentDelivery = $calculatorChangePrice->syncItemWithChangedPrice($this->currentDelivery, $changedPrice);

        return $changedPrice['discountValue'];
    }

    private function isApplicable(Discount $discount): bool
    {
        $isApplicableWithAllBasketItems = $this->input->basketItems->contains(function ($basketItem) use ($discount) {
            return !$this->applicableToBasketItem($discount, $basketItem['id']);
        });

        if (!$isApplicableWithAllBasketItems) {
            return false;
        }

        /** @var DiscountCondition|null $minPriceCondition */
        $minPriceCondition = $discount->conditions->firstWhere('type', DiscountConditionModel::MIN_PRICE_ORDER);
        if (!$minPriceCondition) {
            return true;
        }

        return $this->input->getPriceOrders() >= $minPriceCondition->getMinPrice();
    }
}
