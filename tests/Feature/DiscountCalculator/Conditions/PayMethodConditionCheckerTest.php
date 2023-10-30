<?php

namespace Tests\Feature\DiscountCalculator\Conditions;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\PayMethodConditionChecker;
use App\Services\Calculator\InputCalculator;
use Greensight\Oms\Dto\Payment\PaymentMethod;
use Pim\Core\PimException;
use Tests\CreatesApplication;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

/**
 * @property mixed $paymentMethod
 * @property DiscountCondition $condition
 * @property InputParamsBuilder $inputBuilder
 */
class PayMethodConditionCheckerTest extends TestCase
{
    use CreatesApplication;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $methods = [
            PaymentMethod::B2B_SBERBANK,
            PaymentMethod::CREDITLINE_PAID,
            PaymentMethod::POSCREDIT_PAID,
        ];

        $this->paymentMethod = $methods[array_rand($methods)];

        $this->condition = DiscountConditionMock::create(
            DiscountCondition::PAY_METHOD,
            [DiscountCondition::FIELD_PAYMENT_METHODS => [$this->paymentMethod]]
        );

        $this->inputBuilder = new InputParamsBuilder();
    }

    /**
     * @throws PimException
     */
    public function test_payment_method_valid()
    {
        $this->inputBuilder->setPaymentMethod($this->paymentMethod);
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new PayMethodConditionChecker($input, $this->condition);
        $this->assertTrue($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_payment_method_wrong()
    {
        $this->inputBuilder->setPaymentMethod(PaymentMethod::SBP_RAIFFEISEN);
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new PayMethodConditionChecker($input, $this->condition);
        $this->assertFalse($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_payment_method_not_set()
    {
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new PayMethodConditionChecker($input, $this->condition);
        $this->assertFalse($checker->check());
    }
}
