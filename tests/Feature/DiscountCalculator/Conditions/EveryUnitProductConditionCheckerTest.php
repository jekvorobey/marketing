<?php

namespace Tests\Feature\DiscountCalculator\Conditions;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\EveryUnitProductConditionChecker;
use App\Services\Calculator\InputCalculator;
use Pim\Core\PimException;
use Tests\CreatesApplication;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

/**
 * @property InputParamsBuilder $inputBuilder
 * @property int $offerId
 * @property DiscountCondition $condition
 * @property int $eachCount
 */
class EveryUnitProductConditionCheckerTest extends TestCase
{
    use CreatesApplication;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->offerId = 1;
        $this->eachCount = rand(2, 100);
        $this->condition = DiscountConditionMock::create(
            DiscountCondition::EVERY_UNIT_PRODUCT,
            [
                DiscountCondition::FIELD_OFFER => $this->offerId,
                DiscountCondition::FIELD_COUNT => $this->eachCount,
            ]
        );

        $this->inputBuilder = new InputParamsBuilder();
    }

    /**
     * @throws PimException
     */
    public function test_every_unit_valid()
    {
        $this->inputBuilder->setEachBasketItemCount($this->eachCount);

        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new EveryUnitProductConditionChecker($input, $this->condition);

        $this->assertTrue($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_every_unit_invalid()
    {
        $this->inputBuilder->setEachBasketItemCount(1);

        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new EveryUnitProductConditionChecker($input, $this->condition);

        $this->assertFalse($checker->check());
    }
}
