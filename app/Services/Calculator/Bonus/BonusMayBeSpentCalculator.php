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
        $this->setBonusToEachOffer(null, function ($affectedItem, $changePriceValue) use (&$totalBonusPrice) {
            @([
                'offer_id' => $offerId,
                'bundle_id'   => $bundleId
            ] = $affectedItem);

            if ($bundleId) {
                $item = &$this->input->offers[$offerId]['bundles'][$bundleId];
            } else {
                $item = &$this->input->offers[$offerId];
            }

            $discount = $this->changePrice(
                $item,
                $changePriceValue,
                Discount::DISCOUNT_VALUE_TYPE_RUB,
                false,
                self::LOWEST_POSSIBLE_PRICE
            );
            $totalBonusPrice += $discount * $affectedItem['qty'];
            return $changePriceValue;
        });

        $this->output->maxSpendableBonus = $this->priceToBonus($totalBonusPrice);
    }
}
