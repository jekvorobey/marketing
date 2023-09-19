<?php

namespace Tests\Feature\DiscountCalculator;

use Pim\Core\PimException;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\BrandDiscountMock;

class BrandDiscountCalculatorTest extends DiscountCalculatorTest
{
    /**
     * Скидка на бренд применение
     *
     * @return void
     * @throws PimException
     */
    public function test_brand_discount_apply()
    {
        $brandIds = collect($this->inputParams['basketItems'])->pluck('brandId');
        $brandId = $brandIds->random();
        $count = collect($this->inputParams['basketItems']) //сколько с этим брендом
            ->countBy('brandId')
            ->get($brandId);

        $discount = (new BrandDiscountMock())->create($brandId);

        $calculator = $this->getCalculator();
        $calculator->calculate();

        $this->assertEquals(1, $calculator->getOutput()->appliedDiscounts->count());
        $this->assertEquals(
            $discount->value * $count,
            $calculator->getOutput()->appliedDiscounts->first()['change']
        );
    }

    /**
     * Скидка на бренд НЕ применение, другой brandId
     *
     * @return void
     * @throws PimException
     */
    public function test_brand_discount_not_apply()
    {
        (new BrandDiscountMock())->create(9999);

        $calculator = $this->getCalculator();
        $calculator->calculate();

        $this->assertEquals(0, $calculator->getOutput()->appliedDiscounts->count());
    }
}
