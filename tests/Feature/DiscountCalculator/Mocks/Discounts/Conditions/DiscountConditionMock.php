<?php

namespace Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions;

use App\Models\Discount\DiscountCondition;

class DiscountConditionMock
{
    /**
     * @param int $type
     * @param array $condition
     * @return DiscountCondition
     */
    public static function create(int $type, array $condition): DiscountCondition
    {
        return DiscountCondition::make(self::make($type, $condition));
    }

    /**
     * @param int $type
     * @param array $condition
     * @return array
     */
    public static function make(int $type, array $condition): array
    {
        return [
            'type' => $type,
            'condition' => $condition
        ];
    }
}
