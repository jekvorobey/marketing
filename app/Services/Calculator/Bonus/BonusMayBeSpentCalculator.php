<?php

namespace App\Services\Calculator\Bonus;

class BonusMayBeSpentCalculator extends AbstractBonusCalculator
{
    private int $totalSpentBonusPrice = 0;

    protected function needCalculateBonus(): bool
    {
        return $this->bonusSettingsIsSet();
    }

    public function calculate(): void
    {
        parent::calculate();

        $maxSpendableBonusPrice = min($this->totalSpentBonusPrice, $this->input->customerBonusAmount);

        $this->output->maxSpendableBonus = $this->priceToBonus($maxSpendableBonusPrice);
    }

    protected function getBonusesForSpend(): int
    {
        return $this->input->customerBonusAmount;
    }

    protected function spentBonusForOffer(&$offer, int $spendForOfferItem, int $qty): int
    {
        $spentForOffer = $this->getDiscountForOffer($offer, $spendForOfferItem, $qty);

        $this->totalSpentBonusPrice += $spentForOffer;

        return $spentForOffer;
    }
}
