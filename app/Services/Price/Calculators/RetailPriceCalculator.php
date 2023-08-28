<?php

namespace App\Services\Price\Calculators;

use Greensight\CommonMsa\Dto\RoleDto;
use MerchantManagement\Dto\MerchantPricesDto;

class RetailPriceCalculator extends AbstractPriceCalculator
{
    public function getRole(): int
    {
        return RoleDto::ROLE_SHOWCASE_CUSTOMER;
    }

    public function calculatePrice(): float
    {
        $price = $this->basePrice->price;

        /** @var MerchantPricesDto $relevantMerchantPriceSettings */
        $relevantMerchantPriceSettings = $this->getRelevantMerchantPriceSettings();

        if ($relevantMerchantPriceSettings && $relevantMerchantPriceSettings->valueRetail) {
            if ($this->offer->free_buy && !$this->offer->is_price_hidden) {
                $price = ceil($price + $price * $relevantMerchantPriceSettings->valueRetail / 100);
            } else {
                $price = ceil($price + $price * $relevantMerchantPriceSettings->valueProf / 100);
            }
        }

        return $price;
    }
}
