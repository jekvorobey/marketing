<?php

namespace Tests\Feature\DiscountCalculator\Mocks\Checkers;

use App\Services\Calculator\Discount\Checker\CheckerInterface;

class FalseConditionCheckerMock implements CheckerInterface
{
    public function check(): bool
    {
        return false;
    }
}
