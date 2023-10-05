<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\CalculatorChangePrice;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\DeliveryMethodConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\MinPriceOrderConditionChecker;
use App\Services\Calculator\Discount\Checker\DiscountChecker;

class DeliveryApplier extends AbstractApplier
{
    private array $currentDelivery;

    /**
     * @param $currentDelivery
     * @return void
     */
    public function setCurrentDelivery($currentDelivery): void
    {
        $this->currentDelivery = $currentDelivery;
    }

    /**
     * @return array
     */
    public function getModifiedCurrentDelivery(): array
    {
        return $this->currentDelivery;
    }

    /**
     * @param Discount $discount
     * @param bool $justCalculate
     * @return float|null
     */
    public function apply(Discount $discount, bool $justCalculate = false): ?float
    {
        // BX-6549: скидка на доставку автоматически суммируется со всеми скидками.
        // Оставили вызов метода, чтобы maxValueByDiscount заполнялся,
        // если скидка на доставку все-таки указана в synergy
        $this->applicableToBasket($discount);

        if (!$this->checkConditionGroups($discount)) {
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
            $this->currentDelivery = $calculatorChangePrice->syncItemWithChangedPrice(
                $this->currentDelivery,
                $changedPrice
            );
        }

        return $changedPrice['discountValue'];
    }

    /** Проверка групп условий скидки на доставку */
    private function checkConditionGroups(Discount $discount): bool
    {
        $checker = new DiscountChecker($this->input, $discount);
        $checker
            ->addExtraParam(
                DeliveryMethodConditionChecker::DELIVERY_METHOD_PARAM,
                $this->currentDelivery['method']
            )->addExtraParam(
                MinPriceOrderConditionChecker::USE_PRICE_PARAM,
                true
            );

        return $checker->checkDiscountConditionGroups();
    }
}
