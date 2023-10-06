<?php

namespace Tests\Feature\DiscountCalculator\Mocks\Discounts\WithConditions;

use App\Models\Discount\Discount;
use App\Models\Discount\LogicalOperator;
use App\Services\Discount\DiscountHelper;
use Pim\Core\PimException;

class CartTotalDiscountWithConditionsMock
{
    /**
     * @param array $conditionGroups
     * @param int $logicalOperator
     * @return Discount
     * @throws PimException
     */
    public function create(array $conditionGroups, int $logicalOperator = LogicalOperator::AND): Discount
    {
        $data = [
            'name' => 'Test cart total discount with conditions',
            'user_id' => 1,
            'type' => Discount::DISCOUNT_TYPE_CART_TOTAL,
            'value' => rand(100, 200),
            'value_type' => Discount::DISCOUNT_VALUE_TYPE_RUB,
            'promo_code_only' => false,
            'status' => Discount::STATUS_ACTIVE,
            'conditions_logical_operator' => $logicalOperator,
            'relations' => [
                Discount::DISCOUNT_CONDITION_GROUP_RELATION => $conditionGroups
            ],
            'show_on_showcase' => false,
            'show_original_price' => true,
        ];

        return Discount::find(
            DiscountHelper::create($data)
        );
    }
}
