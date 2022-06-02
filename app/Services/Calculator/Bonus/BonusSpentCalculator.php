<?php

namespace App\Services\Calculator\Bonus;

use Greensight\Oms\Dto\Payment\PaymentMethod;

class BonusSpentCalculator extends AbstractBonusSpentCalculator
{
    protected function needCalculate(): bool
    {
        return $this->bonusSettingsIsSet() && $this->input->bonus > 0 && $this->input->payment['method'] !== PaymentMethod::CREDITPAYMENT;
    }

    protected function getBonusesForSpend(): int
    {
        return $this->input->bonus;
    }

    protected function spentBonusForBasketItem(&$basketItem, int $spendForBasketItem, int $qty): int
    {
        $spentForBasketItem = $this->applyDiscountForBasketItem($basketItem, $spendForBasketItem, $qty);

        $basketItem['bonusSpent'] = $this->priceToBonus($spentForBasketItem);
        $basketItem['bonusDiscount'] = $spentForBasketItem;

        return $spentForBasketItem;
    }
}
