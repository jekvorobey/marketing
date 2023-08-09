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

    public function calculatePrice(float $basePrice): float
    {
        /** @var MerchantPricesDto $relevantMerchantPriceSettings */
        $relevantMerchantPriceSettings = $this->getRelevantMerchantPriceSettings();

        if ($relevantMerchantPriceSettings && $relevantMerchantPriceSettings->valueRetail) {
            $basePrice = ceil($basePrice + $basePrice * $relevantMerchantPriceSettings->valueRetail / 100);
        }

        return $basePrice;
    }
}
