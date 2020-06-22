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
        
        $this->setBonusToEachOffer($price, function (&$offer, $changePriceValue) {
            $discount = $this->changePrice(
                $offer,
                $changePriceValue,
                Discount::DISCOUNT_VALUE_TYPE_RUB,
                true,
                self::LOWEST_POSSIBLE_PRICE
            );
    
            $offer['bonusSpent'] = self::priceToBonus($discount);
            $offer['bonusDiscount'] = $discount;
            
            return $discount;
        });
    }
}
