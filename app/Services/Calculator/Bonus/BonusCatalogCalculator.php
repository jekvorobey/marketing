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
    protected function fetchActiveBonuses()
    {
        $this->bonuses = Bonus::query()
            ->active()
            ->get();

        return $this;
    }
}
