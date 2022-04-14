<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Bonus\Bonus;

/**
 * Class BonusCatalogCalculator
 * @package App\Services\Calculator\Bonus
 */
class BonusCatalogCalculator extends BonusCalculator
{
    /**
     * @return $this
     */
    protected function fetchActiveBonuses(): self
    {
        $this->bonuses = Bonus::query()
            ->where('promo_code_only', false)
            ->where('type', '!=', Bonus::TYPE_CART_TOTAL)
            ->active()
            ->get();

        return $this;
    }
}
