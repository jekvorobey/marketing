<?php

namespace Tests\Feature\DiscountCalculator;

use Pim\Core\PimException;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\CategoryDiscountMock;

class CategoryDiscountCalculatorTest extends DiscountCalculatorTest
{
    /**
     * Скидка на категорию применение
     *
     * @return void
     * @throws PimException
     */
    public function test_category_discount_apply()
    {
        $categoryIds = collect($this->inputParams['basketItems'])->pluck('categoryId');
        $categoryId = $categoryIds->random();
        $count = collect($this->inputParams['basketItems']) //сколько с этой категорией
            ->countBy('categoryId')
            ->get($categoryId);

        $discount = (new CategoryDiscountMock())->create($categoryId);

        $calculator = $this->getCalculator();
        $calculator->calculate();

        $this->assertEquals(1, $calculator->getOutput()->appliedDiscounts->count());
        $this->assertEquals(
            $discount->value * $count,
            $calculator->getOutput()->appliedDiscounts->first()['change']
        );
    }

    /**
     * Скидка на категорию НЕ применение, другой categoryId
     *
     * @return void
     * @throws PimException
     */
    public function test_category_discount_not_apply()
    {
        (new CategoryDiscountMock())->create(9999);

        $calculator = $this->getCalculator();
        $calculator->calculate();

        $this->assertEquals(0, $calculator->getOutput()->appliedDiscounts->count());
    }
}
