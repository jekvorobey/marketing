<?php

namespace Tests\Feature\DiscountCalculator\Mocks\Discounts;

use App\Models\Discount\Discount;
use App\Services\Discount\DiscountHelper;
use Pim\Core\PimException;

class PublicEventDiscountMock
{
    /**
     * @param int $ticketTypeId
     * @return Discount
     * @throws PimException
     */
    public function create(int $ticketTypeId): Discount
    {
        $data = [
            'name' => 'Test public event discount',
            'user_id' => 1,
            'type' => Discount::DISCOUNT_TYPE_MASTERCLASS,
            'value' => rand(100, 200),
            'value_type' => Discount::DISCOUNT_VALUE_TYPE_RUB,
            'promo_code_only' => false,
            'status' => Discount::STATUS_ACTIVE,
            'relations' => [
                Discount::DISCOUNT_PUBLIC_EVENT_RELATION => [
                    [
                        'ticket_type_id' => $ticketTypeId,
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
