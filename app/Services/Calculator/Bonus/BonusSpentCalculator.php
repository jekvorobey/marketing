<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Discount\Discount;
use App\Models\Option\Option;
use App\Services\Calculator\AbstractCalculator;
use Illuminate\Support\Collection;

/**
 * Class BonusSpentCalculator
 * @package App\Services\Calculator\Bonus
 */
class BonusSpentCalculator extends AbstractBonusCalculator
{
    public function calculate()
    {
        if ($this->getOption(Option::KEY_BONUS_PER_RUBLES) <= 0
            || $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT) <= 0
            || $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER) <= 0) {
            return;
        }

        if ($this->input->bonus <= 0) {
            return;
        }

        $price = $this->bonusToPrice($this->input->bonus);
        $this->debiting($price);
    }
    
    /**
     * @return Collection
     */
    protected function sortOffers()
    {
        return $this->input->offers->sortBy(function ($offer) {
           return $this->maxBonusPriceForOffer($offer);
        })->keys();
    }

    /**
     * @param int $price
     */
    protected function debiting(int $price)
    {
        $priceOrder = $this->input->getPriceOrders();
        $maxSpendForOrder = AbstractCalculator::percent($priceOrder, $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER));
        $spendForOrder = min($price, $maxSpendForOrder);

        $offerIds = $this->sortOffers();
        foreach ($offerIds as $offerId) {
            $offer = $this->input->offers[$offerId];
            $maxSpendForOffer = $this->maxBonusPriceForOffer($offer);

            $offerPrice       = $offer['price'];
            $spendForOffer    = AbstractCalculator::percent($spendForOrder, $offerPrice / $priceOrder * 100, AbstractCalculator::ROUND);
            $changePriceValue = min($maxSpendForOffer, $spendForOffer);
            if ($spendForOrder < $changePriceValue * $offer['qty']) {
                $spendForOffer    = AbstractCalculator::percent($spendForOrder, $offerPrice / $priceOrder * 100, AbstractCalculator::FLOOR);
                $changePriceValue = min($maxSpendForOffer, $spendForOffer);
            }

            $discount = $this->changePrice(
                $offer,
                $changePriceValue,
                Discount::DISCOUNT_VALUE_TYPE_RUB,
                true,
                self::LOWEST_POSSIBLE_PRICE
            );

            $spendForOrder -= $discount * $offer['qty'];
            $priceOrder    -= $offerPrice * $offer['qty'];

            $offer['bonusSpent'] = self::priceToBonus($discount);
            $offer['bonusDiscount'] = $discount;
        }
    }
}
