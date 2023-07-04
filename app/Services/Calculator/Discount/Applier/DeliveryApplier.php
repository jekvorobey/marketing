<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
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

    public function apply(Discount $discount, bool $justCalculate = false): ?float
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

        if (!$justCalculate) {
            $this->currentDelivery = $calculatorChangePrice->syncItemWithChangedPrice($this->currentDelivery, $changedPrice);
        }

        return $changedPrice['discountValue'];
    }

    private function isApplicable(Discount $discount): bool
    {
        foreach ($this->input->basketItems as $basketItem) {
            // BX-6549: скидка на доставку автоматически суммируется со всеми скидками
            // Оставил вызов метода, чтобы maxValueByDiscount заполнялся, если скидка на доставку все-таки указаны в synergy
            $this->applicableToBasketItem($discount, $basketItem);
//            if (!$this->applicableToBasketItem($discount, $basketItem['id'])) {
//                return false;
//            }
        }

        return $this->checkConditions($discount);
    }

    /** Проверка условий скидки на доставку */
    private function checkConditions(Discount $discount): bool
    {
        foreach ($discount->conditions as $minPriceCondition) {
            if (!$this->checkCondition($minPriceCondition)) {
                return false;
            }
        }

        return true;
    }

    private function checkCondition(DiscountCondition $condition): bool
    {
        return match ($condition->type) {
            DiscountCondition::MIN_PRICE_ORDER => $this->input->getPriceOrders() >= $condition->getMinPrice(),
            DiscountCondition::MIN_PRICE_BRAND => $this->input->getMaxTotalPriceForBrands($condition->getBrands()) >= $condition->getMinPrice(),
            DiscountCondition::MIN_PRICE_CATEGORY => $this->input->getMaxTotalPriceForCategories($condition->getCategories()) >= $condition->getMinPrice(),
            DiscountCondition::DELIVERY_METHOD => in_array($this->currentDelivery['method'], $condition->getDeliveryMethods()),
            default => true,
        };
    }
}
