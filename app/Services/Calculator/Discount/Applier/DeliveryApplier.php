<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\CalculatorChangePrice;
use Illuminate\Support\Collection;

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
            if (!$this->applicableToBasketItem($discount, $basketItem['id'])) {
                return false;
            }
        }

        /** @var Collection|DiscountCondition[] $minPriceConditions */
        $minPriceConditions = $discount->conditions->whereIn('type', [
            DiscountCondition::MIN_PRICE_ORDER,
            DiscountCondition::MIN_PRICE_BRAND,
            DiscountCondition::MIN_PRICE_CATEGORY,
        ]);

        foreach ($minPriceConditions as $minPriceCondition) {
            if (!$this->checkMinPriceCondition($minPriceCondition)) {
                return false;
            }
        }

        return true;
    }

    private function checkMinPriceCondition(DiscountCondition $condition): bool
    {
        switch ($condition->type) {
            case DiscountCondition::MIN_PRICE_ORDER:
                return $this->input->getPriceOrders() >= $condition->getMinPrice();
            case DiscountCondition::MIN_PRICE_BRAND:
                return $this->input->getMaxTotalPriceForBrands($condition->getBrands()) >= $condition->getMinPrice();
            case DiscountCondition::MIN_PRICE_CATEGORY:
                return $this->input->getMaxTotalPriceForCategories($condition->getCategories()) >= $condition->getMinPrice();
            default:
                return true;
        }
    }
}
