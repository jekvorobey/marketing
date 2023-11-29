<?php

namespace Tests\Feature\DiscountCalculator\Conditions;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\MinPriceOrderConditionChecker;
use App\Services\Calculator\InputCalculator;
use Pim\Core\PimException;
use Tests\CreatesApplication;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

/**
 * @property array|int[] $categories
 * @property array|int[] $minPrice
 * @property DiscountCondition $condition
 * @property InputParamsBuilder $inputBuilder
 */
class MinPriceOrderConditionCheckerTest extends TestCase
{
    use CreatesApplication;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->inputBuilder = new InputParamsBuilder();
    }

    /**
     * @throws PimException
     */
    public function test_min_price_valid()
    {
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $condition = DiscountConditionMock::create(
            DiscountCondition::MIN_PRICE_ORDER,
            [DiscountCondition::FIELD_MIN_PRICE => $input->getOrderPrice() - 10]
        );

        $checker = new MinPriceOrderConditionChecker($input, $condition);

        $this->assertTrue($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_min_price_invalid()
    {
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $condition = DiscountConditionMock::create(
            DiscountCondition::MIN_PRICE_ORDER,
            [DiscountCondition::FIELD_MIN_PRICE => $input->getOrderPrice() + 10,]
        );

        $checker = new MinPriceOrderConditionChecker($input, $condition);

        $this->assertFalse($checker->check());
    }
}
