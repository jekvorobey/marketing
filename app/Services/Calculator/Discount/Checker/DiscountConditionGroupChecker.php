<?php

namespace App\Services\Calculator\Discount\Checker;

use App\Models\Discount\DiscountConditionGroup;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\FalseConditionChecker;
use App\Services\Calculator\Discount\Checker\Resolvers\DiscountConditionCheckerResolver;
use App\Services\Calculator\Discount\Checker\Resolvers\LogicalOperatorCheckerResolver;
use App\Services\Calculator\Discount\Checker\Traits\WithExtraParams;
use App\Services\Calculator\InputCalculator;

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

        foreach ($this->conditionGroup->conditions as $condition) {
            /* если условие в исключенных, то оно будет false */
            $checker = in_array($condition->type, $this->exceptedConditionTypes)
                ? new FalseConditionChecker()
                : app(DiscountConditionCheckerResolver::class)->resolve($condition->type);

            $checker
                ->setInput($this->input)
                ->setCondition($condition)
                ->setExtraParams($this->extraParams);

            $checkers[] = $checker;
        }

        return $checkers;
    }
}
