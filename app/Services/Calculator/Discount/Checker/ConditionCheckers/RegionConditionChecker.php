<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

class RegionConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        return in_array($this->input->getUserRegionId(), $this->condition->getRegions());
    }
}
