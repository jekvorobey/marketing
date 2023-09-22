<?php

namespace App\Services\Calculator\Discount\Checker\Resolvers;

use App\Models\Discount\LogicalOperator;
use App\Services\Calculator\Discount\Checker\LogicalOperatorCheckers\AndChecker;
use App\Services\Calculator\Discount\Checker\LogicalOperatorCheckers\AndNoChecker;
use App\Services\Calculator\Discount\Checker\LogicalOperatorCheckers\LogicalOperatorCheckerInterface;
use App\Services\Calculator\Discount\Checker\LogicalOperatorCheckers\OrChecker;
use App\Services\Calculator\Discount\Checker\LogicalOperatorCheckers\OrNoChecker;

/**
 * Резолвит проверяющий класс в зависимости от типа логического оператора
 */
class LogicalOperatorCheckerResolver
{
    /**
     * @param int $operator
     * @return LogicalOperatorCheckerInterface
     */
    public function resolve(int $operator): LogicalOperatorCheckerInterface
    {
        return match($operator) {
            LogicalOperator::AND => new AndChecker(),
            LogicalOperator::OR => new OrChecker(),
            LogicalOperator::AND_NO => new AndNoChecker(),
            LogicalOperator::OR_NO => new OrNoChecker(),
        };
    }
}
