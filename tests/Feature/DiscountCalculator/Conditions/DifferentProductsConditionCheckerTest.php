<?php

namespace Tests\Feature\DiscountCalculator\Conditions;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\DifferentProductsConditionChecker;
use App\Services\Calculator\Discount\DiscountConditionStore;
use App\Services\Calculator\InputCalculator;
use Pim\Core\PimException;
use Tests\CreatesApplication;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

/**
 * @property DiscountCondition $condition
 * @property InputParamsBuilder $inputBuilder
 * @property int $additionalDiscount
 */
class DifferentProductsConditionCheckerTest extends TestCase
{
    use CreatesApplication;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->additionalDiscount = rand(1, 100);
        $this->condition = DiscountConditionMock::create(
            DiscountCondition::DIFFERENT_PRODUCTS_COUNT,
            [
                DiscountCondition::FIELD_COUNT => 2,
                DiscountCondition::FIELD_ADDITIONAL_DISCOUNT => $this->additionalDiscount,
            ]
        );

        $this->inputBuilder = new InputParamsBuilder();
    }

    /**
     * @throws PimException
     */
    public function test_different_products_count_valid()
    {
        $this->inputBuilder->setBasketItemsCount(2);

        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new DifferentProductsConditionChecker($input, $this->condition);

        $this->assertTrue($checker->check());

        /** @var DiscountCondition $savedCondition */
        $savedCondition = DiscountConditionStore::get(DifferentProductsConditionChecker::STORE_KEY);

        $this->assertEquals(
            $this->additionalDiscount,
            $savedCondition->getAdditionalDiscount()
        );
    }

    /**
     * @throws PimException
     */
    public function test_different_products_count_invalid()
    {
        $this->inputBuilder->setBasketItemsCount(1);

        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new DifferentProductsConditionChecker($input, $this->condition);

        $this->assertFalse($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_different_products_count_invalid_same_product()
    {
        $this
            ->inputBuilder
            ->setBasketItemsCount(1)
            ->setEachBasketItemCount(3);

        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new DifferentProductsConditionChecker($input, $this->condition);

        $this->assertFalse($checker->check());
    }
}
