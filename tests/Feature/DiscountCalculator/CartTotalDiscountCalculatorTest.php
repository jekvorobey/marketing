<?php

namespace Tests\Feature\DiscountCalculator;

use Pim\Core\PimException;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\CartTotalDiscountMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParams;

class CartTotalDiscountCalculatorTest extends DiscountCalculatorTest
{
    /**
     * Скидка на сумму корзину
     *
     * @return void
     * @throws PimException
     */
    public function test_cart_total_discount_apply()
    {
        $discount = (new CartTotalDiscountMock())->create();
        $this->inputParams = (new InputParams())
            ->setBasketItemsCount(20)
            ->baseParamsWithRandomBasketItems();

        $calculator = $this->getCalculator();
        $calculator->calculate();

        $this->assertEquals(1, $calculator->getOutput()->appliedDiscounts->count());
        $this->assertEquals(
            $discount->value,
            $calculator->getOutput()->appliedDiscounts->first()['change']
        );
    }
}
