<?php

namespace App\Services\Calculator\Discount\Checker;

use App\Models\Discount\Discount;
use App\Services\Calculator\Discount\Checker\Resolvers\LogicalOperatorCheckerResolver;
use App\Services\Calculator\Discount\Checker\Traits\WithExtraParams;
use App\Services\Calculator\InputCalculator;

/**
 * Проверка возможности применения скидки
 * Проверка всех групп условий скидки
 */
class DiscountChecker implements CheckerInterface
{
    use WithExtraParams;

    protected array $exceptedConditionTypes = [];

    public function __construct(
        protected InputCalculator $input,
        protected Discount $discount
    ) {}

    /**
     * @return bool
     */
    public function check(): bool
    {
        return $this->checkDiscount() && $this->checkDiscountConditionGroups();
    }

    /**
     * @return bool
     */
    public function checkDiscount(): bool
    {
        return $this->checkType()
            && $this->checkCustomerRole()
            && $this->checkSegment();
    }

    /**
     * @return bool
     */
    public function checkType(): bool
    {
        return match ($this->discount->type) {
            Discount::DISCOUNT_TYPE_OFFER => $this->checkOffers(),
            Discount::DISCOUNT_TYPE_BUNDLE_OFFER,
            Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS => $this->checkBundles(),
            Discount::DISCOUNT_TYPE_BRAND => $this->checkBrands(),
            Discount::DISCOUNT_TYPE_CATEGORY => $this->checkCategories(),
            Discount::DISCOUNT_TYPE_DELIVERY => isset($this->input->deliveries['current']['price']),
            Discount::DISCOUNT_TYPE_MASTERCLASS => $this->input->ticketTypeIds->isNotEmpty()
                && $this->checkPublicEvents(),
            Discount::DISCOUNT_TYPE_CART_TOTAL,
            Discount::DISCOUNT_TYPE_ANY_OFFER,
            Discount::DISCOUNT_TYPE_ANY_BUNDLE,
            Discount::DISCOUNT_TYPE_ANY_BRAND,
            Discount::DISCOUNT_TYPE_ANY_CATEGORY,
            Discount::DISCOUNT_TYPE_ANY_MASTERCLASS => $this->input->basketItems->isNotEmpty(),
            default => false,
        };
    }

    /**
     * TODO: вообще это условие, но хранится как связь
     * @return bool
     */
    public function checkCustomerRole(): bool
    {
        $condition1 = $this->discount->roles->pluck('role_id')->isEmpty();
        $condition2 = isset($this->input->customer['roles'])
            && $this->discount
                ->roles
                ->pluck('role_id')
                ->intersect($this->input->customer['roles'])
                ->isNotEmpty();

        return $condition1 || $condition2;
    }

    /**
     * TODO: вообще это условие, но хранится как связь
     * @return bool
     */
    public function checkSegment(): bool
    {
        // Если отсутствуют условия скидки на сегмент
        if ($this->discount->segments->pluck('segment_id')->isEmpty()) {
            return true;
        }

        return isset($this->input->customer['segment'])
            && $this->discount->segments->contains('segment_id', $this->input->customer['segment']);
    }

    /**
     * Проверяет доступность применения скидки на офферы
     * @return bool
     */
    public function checkOffers(): bool
    {
        return $this->discount->type === Discount::DISCOUNT_TYPE_OFFER
            && $this->discount->offers->where('except', '=', false)->isNotEmpty();
    }

    /**
     * Проверяет доступность применения скидок-бандлов
     * @return bool
     */
    public function checkBundles(): bool
    {
        return in_array($this->discount->type, [
                Discount::DISCOUNT_TYPE_BUNDLE_OFFER,
                Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS
            ]) && $this->discount->bundleItems->isNotEmpty();
    }

    /**
     * Проверяет доступность применения скидки на бренды
     * @return bool
     */
    public function checkBrands(): bool
    {
        return $this->discount->type === Discount::DISCOUNT_TYPE_BRAND
            && $this->discount->brands->filter(fn($brand) => !$brand['except'])->isNotEmpty();
    }

    /**
     * Проверяет доступность применения скидки на категории
     * @return bool
     */
    public function checkCategories(): bool
    {
        return $this->discount->type === Discount::DISCOUNT_TYPE_CATEGORY
            && $this->discount->categories->isNotEmpty();
    }

    /**
     * Проверяет доступность применения скидки на мастер-классы
     * @return bool
     */
    public function checkPublicEvents(): bool
    {
        return $this->discount->type === Discount::DISCOUNT_TYPE_MASTERCLASS
            && $this->discount->publicEvents->isNotEmpty();
    }

    /**
     * @return bool
     */
    public function checkDiscountConditionGroups(): bool
    {
        if ($this->discount->conditionGroups->isEmpty()) {
            return true;
        }

        return app(LogicalOperatorCheckerResolver::class)
            ->resolve($this->discount->conditions_logical_operator)
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
     * Собрать массив из объектов DiscountConditionGroupChecker для проверки
     * @return array
     */
    protected function makeCheckers(): array
    {
        $checkers = [];

        foreach ($this->discount->conditionGroups as $group) {
            $checker = new DiscountConditionGroupChecker($this->input, $group);
            $checker->setExtraParams($this->extraParams);
            $checker->exceptConditionTypes($this->exceptedConditionTypes);
            $checkers[] = $checker;
        }

        return $checkers;
    }

}
