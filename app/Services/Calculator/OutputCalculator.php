<?php

namespace App\Services\Calculator;

/**
 * Class OutputCalculator
 * @package App\Services\Calculator
 */
class OutputCalculator
{
    public $appliedPromoCode;
    public $appliedDiscounts;
    public $appliedBonuses;
    public $maxSpendableBonus;

    /**
     * OutputCalculator constructor.
     */
    public function __construct()
    {
        $this->appliedDiscounts = collect();
        $this->appliedBonuses = collect();
        $this->appliedPromoCode = null;
        $this->maxSpendableBonus = 0;
    }

    /**
     * Была ли применена хотя бы одна скидка
     * @return bool
     */
    public function anyDiscountWasApplied(): bool
    {
        return $this->appliedDiscounts->isNotEmpty();
    }
}
