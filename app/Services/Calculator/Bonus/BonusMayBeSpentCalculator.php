<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Option\Option;

class BonusMayBeSpentCalculator extends AbstractBonusCalculator
{
    public function calculate()
    {
        $maxOrderBonusPercent = $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER);
        $maxOrderBonusPrice = self::percent($this->input->getPriceOrders(), $maxOrderBonusPercent);
        $totalBonusPrice = 0;
        foreach ($this->input->offers as $offer) {
            $bonusOfferPrice = $this->maxBonusPriceForOffer($offer);
            $totalBonusPrice += $bonusOfferPrice * $offer['qty'];
        }
        $resultOrderBonusPrice = min($maxOrderBonusPrice, $totalBonusPrice);
        $this->output->maxSpendableBonus = $this->priceToBonus($resultOrderBonusPrice);
    }
}