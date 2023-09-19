<?php

namespace Tests\Feature\DiscountCalculator\Mocks\Discounts;

use App\Models\Discount\Discount;
use App\Services\Discount\DiscountHelper;
use Pim\Core\PimException;

class ProductDiscountMock
{
    /**
     * @param int $offerId
     * @return Discount
     * @throws PimException
     */
    public function create(int $offerId): Discount
    {
        $data = [
            'name' => 'Test product discount',
            'user_id' => 1,
            'type' => Discount::DISCOUNT_TYPE_OFFER,
            'value' => rand(100, 200),
            'value_type' => Discount::DISCOUNT_VALUE_TYPE_RUB,
            'promo_code_only' => false,
            'status' => Discount::STATUS_ACTIVE,
            'relations' => [
                Discount::DISCOUNT_OFFER_RELATION => [
                    [
                        'offer_id' => $offerId,
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
