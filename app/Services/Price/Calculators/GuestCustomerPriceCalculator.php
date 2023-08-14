<?php

namespace App\Services\Price\Calculators;

use Greensight\CommonMsa\Dto\RoleDto;
use MerchantManagement\Dto\MerchantPricesDto;

class GuestCustomerPriceCalculator extends RetailPriceCalculator
{
    public function getRole(): int
    {
        return RoleDto::ROLE_SHOWCASE_GUEST;
    }
}
