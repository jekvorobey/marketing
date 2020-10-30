<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Discount\Discount;

/**
 * Class BonusSpentCalculator
 * @package App\Services\Calculator\Bonus
 */
class BonusSpentCalculator extends AbstractBonusCalculator
{
    public function calculate()
    {
        if (!$this->needCalculateBonus()) {
            return;
        }

        $price = $this->bonusToPrice($this->input->bonus);

        $this->setBonusToEachOffer($price, function ($affectedItem, $changePriceValue) {
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
                true,
                self::LOWEST_POSSIBLE_PRICE
            );

            $item['bonusSpent'] = self::priceToBonus($discount);
            $item['bonusDiscount'] = $discount;

            return $discount;
        });
    }
}
