<?php

namespace App\Services\Calculator\Discount\Checker\LogicalOperatorCheckers;

use App\Services\Calculator\Discount\Checker\CheckerInterface;

interface LogicalOperatorCheckerInterface
{
    /**
     * Проверка нескольких условий
     * @param CheckerInterface[] $checkers - проверяющие классы
     * @return bool
     */
    public function check(array $checkers): bool;
}
