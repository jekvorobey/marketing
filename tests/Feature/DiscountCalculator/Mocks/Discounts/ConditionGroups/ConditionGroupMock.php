<?php

namespace tests\Feature\DiscountCalculator\Mocks\Discounts\ConditionGroups;

class ConditionGroupMock
{
    /**
     * @param int $logicalOperator
     * @param array $conditions
     * @return array
     */
    public static function create(int $logicalOperator, array $conditions): array
    {
        return [
            'logical_operator' => $logicalOperator,
            'conditions' => $conditions
        ];
    }
}
