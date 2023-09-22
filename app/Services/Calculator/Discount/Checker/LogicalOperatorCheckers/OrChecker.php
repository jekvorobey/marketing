<?php

namespace App\Services\Calculator\Discount\Checker\LogicalOperatorCheckers;

use App\Services\Calculator\Discount\Checker\CheckerInterface;

class OrChecker implements LogicalOperatorCheckerInterface
{
    /**
     * @param CheckerInterface[] $checkers
     * @return bool
     */
    public function check(array $checkers): bool
    {
        if (empty($checkers)) {
            return true;
        }

        foreach ($checkers as $checker) {
            if ($checker->check()) {
                return true;
            }
        }

        return false;
    }
}
