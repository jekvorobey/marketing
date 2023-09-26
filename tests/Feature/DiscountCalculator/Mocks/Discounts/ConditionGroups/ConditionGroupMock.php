<?php

namespace Tests\Feature\DiscountCalculator\Mocks\Discounts\ConditionGroups;

class ConditionGroupMock
{
    /**
     * @param int $logicalOperator
     * @param array $conditions
     * @return array
     */
    public static function make(int $logicalOperator, array $conditions): array
    {
        return [
            'logical_operator' => $logicalOperator,
            'conditions' => $conditions
        ];
    }
}
