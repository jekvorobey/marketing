<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

/**
 * Класс заглушка всегда FALSE
 */
class FalseConditionChecker extends AbstractConditionChecker
{
    /**
     * @return bool
     */
    public function check(): bool
    {
        return false;
    }
}
