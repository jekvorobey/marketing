<?php

namespace App\Services\Price\Calculators;

use Greensight\CommonMsa\Dto\RoleDto;
use MerchantManagement\Dto\MerchantPricesDto;

class ProfPriceCalculator extends AbstractPriceCalculator
{
    public function getRole(): int
    {
        return RoleDto::ROLE_SHOWCASE_PROFESSIONAL;
    }

    public function calculatePrice(float $basePrice): float
    {
        /** @var MerchantPricesDto $relevantMerchantPriceSettings */
        $relevantMerchantPriceSettings = $this->getRelevantMerchantPriceSettings();

        if ($relevantMerchantPriceSettings && $relevantMerchantPriceSettings->valueProf) {
            $basePrice = ceil($basePrice + $basePrice * $relevantMerchantPriceSettings->valueProf / 100);
        }

        return $basePrice;
    }
}
