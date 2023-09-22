<?php

namespace App\Services\Calculator\Discount\Checker;

use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountConditionGroup;
use App\Services\Calculator\Discount\Checker\Traits\WithExtraParams;
use App\Services\Calculator\InputCalculator;
use Illuminate\Support\Collection;

/**
 * Проверяет группу условий скидки
 */
class DiscountConditionGroupChecker implements CheckerInterface
{
    use WithExtraParams;

    private array $exceptedConditionTypes = [];
    public function __construct(
        protected InputCalculator $input,
        protected DiscountConditionGroup $conditionGroup
    ) {}

    /**
     * @return bool
     */
    public function check(): bool
    {
        if ($this->getFilteredConditions()->isEmpty()) {
            return true;
        }

        return app(LogicalOperatorCheckerResolver::class)
            ->resolve($this->conditionGroup->logical_operator)
            ->check($this->makeCheckers());
    }

    /**
     * Типы условий, которые нужно исключить из проверки
     * @param array $types
     * @return $this
     */
    public function exceptConditionTypes(array $types): static
    {
        $this->exceptedConditionTypes = $types;
        return $this;
    }

    /**
     * @return array
     */
    private function makeCheckers(): array
    {
        $checkers = [];

        foreach ($this->getFilteredConditions() as $condition) {
            $checker = new DiscountConditionChecker($this->input, $condition);
            $checker->setExtraParams($this->extraParams);
            $checkers[] = $checker;
        }

        return $checkers;
    }

    /**
     * @return Collection
     */
    protected function getFilteredConditions(): Collection
    {
        return $this->conditionGroup->conditions->reject(
            fn (DiscountCondition $condition) => in_array($condition->type, $this->exceptedConditionTypes)
        );
    }
}
