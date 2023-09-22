<?php

namespace App\Services\Calculator\Discount\Checker\LogicalOperatorCheckers;

use App\Services\Calculator\Discount\Checker\CheckerInterface;

class AndChecker implements LogicalOperatorCheckerInterface
{
    /**
     * @param CheckerInterface[] $checkers
     * @return bool
     */
    public function check(array $checkers): bool
    {
        foreach ($checkers as $checker) {
            if (!$checker->check()) {
                return false;
            }
        }

        return true;
    }
}
