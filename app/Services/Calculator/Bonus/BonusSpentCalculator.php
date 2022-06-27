<?php

namespace App\Services\Calculator\Bonus;

class BonusSpentCalculator extends AbstractBonusSpentCalculator
{
    protected function needCalculate(): bool
    {
        return parent::needCalculate() && $this->getBonusesForSpend() > 0;
    }

    protected function getBonusesForSpend(): int
    {
        return $this->input->bonus;
    }

    protected function spentBonusForBasketItem(&$basketItem, int $spendForBasketItem, int $qty): int
    {
        $spentForBasketItem = $this->applyDiscountForBasketItem($basketItem, $spendForBasketItem, $qty);

        $basketItem['bonusSpent'] = $this->priceToBonus($spentForBasketItem);
        $basketItem['bonusDiscount'] = $spentForBasketItem;

        return $spentForBasketItem;
    }
}
