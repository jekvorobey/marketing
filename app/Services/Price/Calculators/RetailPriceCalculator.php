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
            $price = ceil($price + $price * $relevantMerchantPriceSettings->valueRetail / 100);
        }

        return $price;
    }
}
