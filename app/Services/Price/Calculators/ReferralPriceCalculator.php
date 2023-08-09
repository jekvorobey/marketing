<?php

namespace App\Services\Price\Calculators;

use Greensight\CommonMsa\Dto\RoleDto;

class ReferralPriceCalculator extends ProfPriceCalculator
{
    public function getRole(): int
    {
        return RoleDto::ROLE_SHOWCASE_REFERRAL_PARTNER;
    }
}
