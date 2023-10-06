<?php

namespace Tests\Feature\DiscountCalculator;

use App\Services\Calculator\Discount\Calculators\DiscountCalculator;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;
use Pim\Core\PimException;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\ProductDiscountMock;

class ProductDiscountCalculatorTest extends DiscountCalculatorTest
{
    /**
     * Скидка на продукт применение
     *
     * @return void
     * @throws PimException
     */
    public function test_product_discount_apply()
    {
        $offerIds = collect($this->inputParams['basketItems'])->pluck('offerId');
        $offerId = $offerIds->random();
        $discount = (new ProductDiscountMock())->create($offerId);

        $calculator = $this->getCalculator();
        $calculator->calculate();

        $this->assertEquals(1, $calculator->getOutput()->appliedDiscounts->count());
        $this->assertEquals($discount->value, $calculator->getOutput()->appliedDiscounts->first()['change']);
    }

    /**
     * Скидка на продукт НЕ применение, другой offerId
     *
     * @return void
     * @throws PimException
     */
    public function test_product_discount_not_apply()
    {
        (new ProductDiscountMock())->create(9999);

        $calculator = new DiscountCalculator(
            new InputCalculator($this->inputParams),
            new OutputCalculator()
        );

        $calculator->calculate();

        $this->assertEquals(0, $calculator->getOutput()->appliedDiscounts->count());
    }
}
