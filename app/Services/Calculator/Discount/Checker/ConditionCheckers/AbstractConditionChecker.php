<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\CheckerInterface;
use App\Services\Calculator\Discount\Checker\Traits\WithExtraParams;
use App\Services\Calculator\InputCalculator;

abstract class AbstractConditionChecker implements CheckerInterface
{
    use WithExtraParams;

    public function __construct(
        protected InputCalculator $input,
        protected DiscountCondition $condition
    ) {}
}
