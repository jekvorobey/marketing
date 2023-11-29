<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\CheckerInterface;
use App\Services\Calculator\Discount\Checker\Traits\WithExtraParams;
use App\Services\Calculator\Discount\DiscountConditionStore;
use App\Services\Calculator\InputCalculator;

abstract class AbstractConditionChecker implements CheckerInterface
{
    use WithExtraParams;

    public function __construct(
        protected ?InputCalculator $input = null,
        protected ?DiscountCondition $condition = null
    ) {}

    /**
     * @param InputCalculator $input
     * @return $this
     */
    public function setInput(InputCalculator $input): AbstractConditionChecker
    {
        $this->input = $input;
        return $this;
    }

    /**
     * @param DiscountCondition $condition
     * @return $this
     */
    public function setCondition(DiscountCondition $condition): AbstractConditionChecker
    {
        $this->condition = $condition;
        return $this;
    }

    /**
     * Сохраняем в стор, чтобы потом проверять при применении к каждому basketItem
     * @return void
     */
    protected function saveConditionToStore(): void
    {
        DiscountConditionStore::put(spl_object_hash($this->condition), $this->condition);
    }
}
