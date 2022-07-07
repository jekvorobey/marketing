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
        foreach ($this->input->basketItems as $basketItem) {
            // BX-6549: скидка на доставку автоматически суммируется со всеми скидками
            // Оставил вызов метода, чтобы maxValueByDiscount заполнялся, если скидка на доставку все-таки указаны в synergy
            $this->applicableToBasketItem($discount, $basketItem['id']);
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
        switch ($condition->type) {
            case DiscountCondition::MIN_PRICE_ORDER:
                return $this->input->getPriceOrders() >= $condition->getMinPrice();
            case DiscountCondition::MIN_PRICE_BRAND:
                return $this->input->getMaxTotalPriceForBrands($condition->getBrands()) >= $condition->getMinPrice();
            case DiscountCondition::MIN_PRICE_CATEGORY:
                return $this->input->getMaxTotalPriceForCategories($condition->getCategories()) >= $condition->getMinPrice();
            case DiscountCondition::DELIVERY_METHOD:
                return in_array($this->currentDelivery['method'], $condition->getDeliveryMethods());
            default:
                return true;
        }
    }
}
