<?php

namespace App\Services\Calculator\Discount\Checker;

use App\Models\Discount\Discount;
use App\Services\Calculator\InputCalculator;

abstract class BaseDiscountConditionChecker
{
    protected InputCalculator $input;

    public function __construct(InputCalculator $inputCalculator)
    {
        $this->input = $inputCalculator;
    }

    abstract public function check(Discount $discount, array $checkingConditionTypes = []): bool;
}
