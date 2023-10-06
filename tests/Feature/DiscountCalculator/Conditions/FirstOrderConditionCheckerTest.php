<?php

namespace Tests\Feature\DiscountCalculator\Conditions;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\FirstOrderConditionChecker;
use App\Services\Calculator\InputCalculator;
use Greensight\Oms\Dto\OrderStatus;
use Greensight\Oms\Services\OrderService\OrderService;
use Pim\Core\PimException;
use Tests\CreatesApplication;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

/**
 * @property int|null $customerId
 * @property DiscountCondition $condition
 * @property InputParamsBuilder $inputBuilder
 */
class FirstOrderConditionCheckerTest extends TestCase
{
    use CreatesApplication;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);
        $query = $orderService->newQuery()
            ->pageNumber(1, 10)
            ->setFilter('status', [
                OrderStatus::DELIVERING,
                OrderStatus::READY_FOR_RECIPIENT,
                OrderStatus::DONE
            ]);
        $this->customerId = $orderService->orders($query)->first()?->customer_id;

        $this->condition = DiscountConditionMock::create(DiscountCondition::FIRST_ORDER, []);
        $this->inputBuilder = new InputParamsBuilder();
    }

    /**
     * @throws PimException
     */
    public function test_first_order_valid()
    {
        $this->inputBuilder->setCustomerId(99999999999);

        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new FirstOrderConditionChecker($input, $this->condition);
        $this->assertTrue($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_first_order_invalid()
    {
        $this->inputBuilder->setCustomerId($this->customerId);

        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new FirstOrderConditionChecker($input, $this->condition);
        $this->assertFalse($checker->check());
    }
}
