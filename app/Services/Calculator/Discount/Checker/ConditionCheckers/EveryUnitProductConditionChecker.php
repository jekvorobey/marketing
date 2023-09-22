<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class EveryUnitProductConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        return $this->checkEveryUnitProduct($this->condition->getOffer(), $this->condition->getCount());
    }

    /**
     * Количество единиц одного оффера
     */
    public function checkEveryUnitProduct($offerId, $count): bool
    {
        $basketItemByOfferId = $this->input
            ->basketItems
            ->where('offer_id', $offerId)
            ->where('bundle_id', 0)
            ->first();

        return $basketItemByOfferId && $basketItemByOfferId['qty'] >= $count;
    }
}
