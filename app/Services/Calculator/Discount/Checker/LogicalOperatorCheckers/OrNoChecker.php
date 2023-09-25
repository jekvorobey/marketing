<?php

namespace App\Services\Calculator\Discount\Checker\LogicalOperatorCheckers;

use App\Services\Calculator\Discount\Checker\CheckerInterface;

class OrNoChecker implements LogicalOperatorCheckerInterface
{
    /**
     * @param CheckerInterface[] $checkers
     * @return bool
     */
    public function check(array $checkers): bool
    {
        $firstChecker = array_shift($checkers);

        if ($firstChecker->check()) {
            return true;
        }

        foreach ($checkers as $checker) {
            if (!$checker->check()) {
                return true;
            }
        }

        return false;
    }
}
