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

        if ($relevantMerchantPriceSettings) {
            if ($this->offer->free_buy && !$this->offer->is_price_hidden && $relevantMerchantPriceSettings->valueRetail) {
                $price = ceil($price + $price * $relevantMerchantPriceSettings->valueRetail / 100);
            } else if ($relevantMerchantPriceSettings->valueProf) {
                $price = ceil($price + $price * $relevantMerchantPriceSettings->valueProf / 100);
            }
        }

        return $price;
    }
}
