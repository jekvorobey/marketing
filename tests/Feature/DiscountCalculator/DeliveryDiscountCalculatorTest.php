<?php

namespace Tests\Feature\DiscountCalculator;

use Pim\Core\PimException;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\CartTotalDiscountMock;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\DeliveryDiscountMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParams;

class DeliveryDiscountCalculatorTest extends DiscountCalculatorTest
{
    /**
     * Скидка на доставку
     *
     * @return void
     * @throws PimException
     */
    public function test_delivery_discount_apply()
    {
        $discount = (new DeliveryDiscountMock())->create();
        $this->inputParams = (new InputParams())
            ->setBasketItemsCount(5)
            ->baseCheckoutParams();

        $calculator = $this->getCalculator();
        $calculator->calculate();

        $this->assertEquals(1, $calculator->getOutput()->appliedDiscounts->count());
        $this->assertEquals(
            $discount->value,
            $calculator->getOutput()->appliedDiscounts->first()['change']
        );
    }
}
