<?php

namespace App\Services\Calculator\Bonus;

class BonusMayBeSpentCalculator extends AbstractBonusSpentCalculator
{
    private int $totalSpentBonusPrice = 0;

    public function calculate(): void
    {
        parent::calculate();

        $maxSpendableBonusPrice = min($this->totalSpentBonusPrice, $this->getBonusesForSpend());

        $this->output->maxSpendableBonus = $this->priceToBonus($maxSpendableBonusPrice);
    }

    protected function getBonusesForSpend(): int
    {
        return $this->input->customer['bonus'] ?? 0;
    }

    protected function spentBonusForBasketItem(&$basketItem, int $spendForBasketItem, int $qty): int
    {
        $spentForBasketItem = $this->getDiscountForBasketItem($basketItem, $spendForBasketItem, $qty);

        $this->totalSpentBonusPrice += $spentForBasketItem;

        return $spentForBasketItem;
    }
}
