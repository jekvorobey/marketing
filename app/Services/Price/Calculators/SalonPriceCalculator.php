<?php

namespace App\Services\Price\Calculators;

use Greensight\CommonMsa\Dto\RoleDto;

class SalonPriceCalculator extends AbstractPriceCalculator
{
    public function getRole(): int
    {
        return RoleDto::ROLE_SHOWCASE_SALON;
    }

    public function calculatePrice(): float
    {
        return $this->basePrice->price;
    }
}
