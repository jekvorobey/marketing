<?php

namespace Tests\Feature\DiscountCalculator\LogicalOperators;

use App\Services\Calculator\Discount\Checker\LogicalOperatorCheckers\AndChecker;
use Tests\Feature\DiscountCalculator\Mocks\Checkers\FalseConditionCheckerMock;
use Tests\Feature\DiscountCalculator\Mocks\Checkers\TrueConditionCheckerMock;
use Tests\TestCase;

class AndOperatorTest extends TestCase
{
    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->checker = new AndChecker();
    }

    /**
     * @return void
     */
    public function test_true_true(): void
    {
        $this->assertTrue($this->checker->check([
            new TrueConditionCheckerMock(),
            new TrueConditionCheckerMock()
        ]));
    }

    /**
     * @return void
     */
    public function test_true_false(): void
    {
        $this->assertFalse($this->checker->check([
            new TrueConditionCheckerMock(),
            new FalseConditionCheckerMock()
        ]));
    }

    /**
     * @return void
     */
    public function test_false_false(): void
    {
        $this->assertFalse($this->checker->check([
            new FalseConditionCheckerMock(),
            new FalseConditionCheckerMock()
        ]));
    }

    /**
     * @return void
     */
    public function test_many_conditions(): void
    {
        $this->assertFalse($this->checker->check([
            new FalseConditionCheckerMock(),
            new TrueConditionCheckerMock(),
            new FalseConditionCheckerMock(),
            new TrueConditionCheckerMock(),
        ]));
    }
}
