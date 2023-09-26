<?php

namespace Tests\Feature\DiscountCalculator;

use App\Models\Discount\DiscountCondition;
use App\Models\Discount\LogicalOperator;
use Greensight\Oms\Dto\Payment\PaymentMethod;
use Pim\Core\PimException;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\ConditionGroups\ConditionGroupMock;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\WithConditions\CartTotalDiscountWithConditionsMock;

/**
 * @property \App\Models\Discount\Discount $discount
 * @property int $customerId1
 * @property int $customerId2
 * @property int $customerId3
 */
class CartTotalDiscountWithConditionsTest extends DiscountCalculatorTest
{
    /**
     * @return void
     * @throws \Throwable
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->customerId1 = 1;
        $this->customerId2 = 2;
        $this->customerId3 = 3;

        $conditions1Group = [
            DiscountConditionMock::make(
                DiscountCondition::CUSTOMER,
                [DiscountCondition::FIELD_CUSTOMER_IDS => [$this->customerId1]]
            ),
            DiscountConditionMock::make(
                DiscountCondition::MIN_PRICE_ORDER,
                [DiscountCondition::FIELD_MIN_PRICE => 1]
            ),
        ];
        $conditionGroup1 = ConditionGroupMock::make(
            LogicalOperator::AND,
            $conditions1Group
        );

        $conditions2Group = [
            DiscountConditionMock::make(
                DiscountCondition::CUSTOMER,
                [DiscountCondition::FIELD_CUSTOMER_IDS => [$this->customerId2]]
            ),
            DiscountConditionMock::make(
                DiscountCondition::PAY_METHOD,
                [
                    DiscountCondition::FIELD_PAYMENT_METHODS => [
                        PaymentMethod::B2B_SBERBANK,
                        PaymentMethod::CREDITLINE_PAID,
                        PaymentMethod::POSCREDIT_PAID,
                    ]
                ]
            ),
        ];
        $conditionGroup2 = ConditionGroupMock::make(
            LogicalOperator::AND,
            $conditions2Group
        );

        $this->discount = (new CartTotalDiscountWithConditionsMock())->create(
            [$conditionGroup1, $conditionGroup2],
            LogicalOperator::OR
        );
        $this->discount->load('conditionGroups.conditions');
    }

    /**
     * @throws PimException
     */
    public function test_discount_with_conditions_1_group_valid()
    {
        $this->inputBuilder
            ->setCustomerId($this->customerId1)
            ->setPaymentMethod(PaymentMethod::SBP_RAIFFEISEN);

        $this->inputParams = $this->inputBuilder->build();

        $calculator = $this->getCalculator();
        $calculator->calculate();
        $this->assertEquals(1, $calculator->getOutput()->appliedDiscounts->count());
    }

    /**
     * @throws PimException
     */
    public function test_discount_with_conditions_2_group_valid()
    {
        $this->inputBuilder
            ->setCustomerId($this->customerId2)
            ->setPaymentMethod(PaymentMethod::B2B_SBERBANK);

        $this->inputParams = $this->inputBuilder->build();

        $calculator = $this->getCalculator();
        $calculator->calculate();
        $this->assertEquals(1, $calculator->getOutput()->appliedDiscounts->count());
    }

    /**
     * @throws PimException
     */
    public function test_discount_with_conditions_invalid()
    {
        $this->inputBuilder
            ->setCustomerId($this->customerId3)
            ->setPaymentMethod(PaymentMethod::B2B_SBERBANK);

        $this->inputParams = $this->inputBuilder->build();

        $calculator = $this->getCalculator();
        $calculator->calculate();
        $this->assertEquals(0, $calculator->getOutput()->appliedDiscounts->count());
    }
}
