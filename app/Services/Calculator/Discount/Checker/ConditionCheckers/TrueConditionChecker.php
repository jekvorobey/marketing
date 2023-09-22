<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

/**
 * Класс заглушка всегда TRUE
 */
class TrueConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        return true;
    }
}
