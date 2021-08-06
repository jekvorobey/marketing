<?php

namespace App\Services\Calculator\Bonus;

class BonusSpentCalculator extends AbstractBonusCalculator
{
    protected function needCalculateBonus(): bool
    {
        return $this->bonusSettingsIsSet() && $this->input->bonus > 0;
    }

    protected function getBonusesForSpend(): int
    {
        return $this->input->bonus;
    }

    protected function spentBonusForOffer(&$offer, int $spendForOfferItem, int $qty): int
    {
        $spentForOffer = $this->applyDiscountForOffer($offer, $spendForOfferItem, $qty);

        $offer['bonusSpent'] = $this->priceToBonus($spentForOffer);
        $offer['bonusDiscount'] = $spentForOffer;

        return $spentForOffer;
    }
}
