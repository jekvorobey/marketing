<?php

namespace Tests\Feature\DiscountCalculator\Mocks\Discounts;

use App\Models\Discount\Discount;
use App\Services\Discount\DiscountHelper;
use Pim\Core\PimException;

class BrandDiscountMock
{
    /**
     * @param int $brandId
     * @return Discount
     * @throws PimException
     */
    public function create(int $brandId): Discount
    {
        $data = [
            'name' => 'Test brand discount',
            'user_id' => 1,
            'type' => Discount::DISCOUNT_TYPE_BRAND,
            'value' => rand(100, 200),
            'value_type' => Discount::DISCOUNT_VALUE_TYPE_RUB,
            'promo_code_only' => false,
            'status' => Discount::STATUS_ACTIVE,
            'relations' => [
                Discount::DISCOUNT_BRAND_RELATION => [
                    [
                        'brand_id' => $brandId,
                        'except' => false
                    ],
                ],
            ],
            'show_on_showcase' => false,
            'show_original_price' => true,
        ];

        return Discount::find(
            DiscountHelper::create($data)
        );
    }
}
