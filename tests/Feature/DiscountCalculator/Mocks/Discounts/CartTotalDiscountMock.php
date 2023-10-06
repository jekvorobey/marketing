<?php

namespace Tests\Feature\DiscountCalculator\Mocks\Discounts;

use App\Models\Discount\Discount;
use App\Services\Discount\DiscountHelper;
use Pim\Core\PimException;

class CartTotalDiscountMock
{
    /**
     * @return Discount
     * @throws PimException
     */
    public function create(): Discount
    {
        $data = [
            'name' => 'Test cart total discount',
            'user_id' => 1,
            'type' => Discount::DISCOUNT_TYPE_CART_TOTAL,
            'value' => rand(100, 200),
            'value_type' => Discount::DISCOUNT_VALUE_TYPE_RUB,
            'promo_code_only' => false,
            'status' => Discount::STATUS_ACTIVE,
            'relations' => [],
            'show_on_showcase' => false,
            'show_original_price' => true,
        ];

        return Discount::find(
            DiscountHelper::create($data)
        );
    }
}
