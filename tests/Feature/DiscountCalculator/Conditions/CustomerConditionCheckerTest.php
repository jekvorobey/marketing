<?php

namespace Tests\Feature\DiscountCalculator\Conditions;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\CustomerConditionChecker;
use App\Services\Calculator\InputCalculator;
use Pim\Core\PimException;
use Tests\CreatesApplication;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

/**
 * @property array $customerIds
 * @property int $customerId
 * @property DiscountCondition $condition
 * @property InputParamsBuilder $inputBuilder
 */
class CustomerConditionCheckerTest extends TestCase
{
    use CreatesApplication;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->customerIds = range(1, 100);
        $this->customerId = collect($this->customerIds)->random();
        $this->condition = DiscountConditionMock::create(
            DiscountCondition::CUSTOMER,
            [DiscountCondition::FIELD_CUSTOMER_IDS => $this->customerIds]
        );
        $this->inputBuilder = new InputParamsBuilder();
    }

    /**
     * @throws PimException
     */
    public function test_customer_valid(): void
    {
        $this->inputBuilder->setCustomerId($this->customerId);

        $input = new InputCalculator(
            $this->inputBuilder->build()
        );
        $checker = new CustomerConditionChecker($input, $this->condition);

        $this->assertTrue($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_customer_invalid(): void
    {
        $this->inputBuilder->setCustomerId(101);

        $input = new InputCalculator(
            $this->inputBuilder->build()
        );
        $checker = new CustomerConditionChecker($input, $this->condition);

        $this->assertFalse($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_no_customer(): void
    {
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new CustomerConditionChecker($input, $this->condition);

        $this->assertFalse($checker->check());
    }
}
