<?php

namespace Tests\Feature\DiscountCalculator\Conditions;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\DeliveryMethodConditionChecker;
use App\Services\Calculator\InputCalculator;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Pim\Core\PimException;
use Tests\CreatesApplication;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

/**
 * @property array|int|string $deliveryMethod
 * @property DiscountCondition $condition
 * @property InputParamsBuilder $inputBuilder
 */
class DeliveryMethodConditionCheckerTest extends TestCase
{
    use CreatesApplication;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $methods = [
            DeliveryMethod::METHOD_DELIVERY,
            DeliveryMethod::METHOD_PICKUP,
        ];

        $this->deliveryMethod = $methods[array_rand($methods)];

        $this->condition = DiscountConditionMock::create(
            DiscountCondition::DELIVERY_METHOD,
            [DiscountCondition::FIELD_DELIVERY_METHODS => [$this->deliveryMethod]]
        );

        $this->inputBuilder = new InputParamsBuilder();
    }

    /**
     * @throws PimException
     */
    public function test_delivery_method_valid()
    {
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new DeliveryMethodConditionChecker($input, $this->condition);
        $checker->addExtraParam(
            DeliveryMethodConditionChecker::KEY_DELIVERY_METHOD,
            $this->deliveryMethod
        );
        $this->assertTrue($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_delivery_method_wrong()
    {
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new DeliveryMethodConditionChecker($input, $this->condition);
        $checker->addExtraParam(
            DeliveryMethodConditionChecker::KEY_DELIVERY_METHOD,
            999
        );
        $this->assertFalse($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_delivery_method_not_set()
    {
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new DeliveryMethodConditionChecker($input, $this->condition);

        $this->assertFalse($checker->check());
    }
}
