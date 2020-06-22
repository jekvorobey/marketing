<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Discount\Discount;

class BonusMayBeSpentCalculator extends AbstractBonusCalculator
{
    public function calculate()
    {
        if (!$this->bonusSettingsIsSet()) {
            return;
        }
        $totalBonusPrice = 0;
        $this->setBonusToEachOffer(null, function (&$offer, $changePriceValue) use (&$totalBonusPrice) {
            $discount = $this->changePrice(
                $offer,
                $changePriceValue,
                Discount::DISCOUNT_VALUE_TYPE_RUB,
                false,
                self::LOWEST_POSSIBLE_PRICE
            );
            $totalBonusPrice += $discount * $offer['qty'];
            return $changePriceValue;
        });
        
        $this->output->maxSpendableBonus = $this->priceToBonus($totalBonusPrice);
    }
}