<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\DiscountConditionStore;
use App\Services\Calculator\InputCalculator;
use Illuminate\Support\Collection;

abstract class AbstractApplier
{
    /**
     * Если по какой-то скидке есть ограничение на максимальный итоговый размер скидки по офферу
     * @var array
     * [
     *      discount_id => [
     *          'value' => value,
     *          'value_type' => value_type
     *      ],
     *      ...
     * ]
     */
    protected array $maxValueByDiscount = [];
    protected InputCalculator $input;
    protected Collection $basketItemsByDiscounts;
    protected Collection $appliedDiscounts;

    public function __construct(
        InputCalculator $input,
        Collection $basketItemsByDiscounts,
        Collection $appliedDiscounts
    ) {
        $this->input = $input;
        $this->basketItemsByDiscounts = $basketItemsByDiscounts;
        $this->appliedDiscounts = $appliedDiscounts;
    }

    /**
     * @param Discount $discount
     * @return float|null
     */
    abstract public function apply(Discount $discount): ?float;

    /**
     * @return Collection
     */
    public function getModifiedBasketItemsByDiscounts(): Collection
    {
        return $this->basketItemsByDiscounts;
    }

    /**
     * @return Collection
     */
    public function getModifiedInputBasketItems(): Collection
    {
        return $this->input->basketItems;
    }

    /**
     * @param Discount $discount
     * @return void
     */
    protected function applicableToBasket(Discount $discount): void
    {
        foreach ($this->input->basketItems as $basketItem) {
            $this->applicableToBasketItem($discount, $basketItem);
        }
    }

    /**
     * Можно ли применить скидку к элементу корзины
     * @param Discount $discount
     * @param Collection $basketItem
     * @return bool
     */
    protected function applicableToBasketItem(Discount $discount, Collection $basketItem): bool
    {
        if ($this->discountHasTrueMerchantCondition($discount)
            && !$this->basketItemSatisfiesMerchantCondition($discount, $basketItem)) {
            return false;
        }

        if ($this->appliedDiscounts->isEmpty() || !$this->basketItemsByDiscounts->has($basketItem->get('id'))) {
            return true;
        }

        // для скидок промокода проверяется при применении
        if ($discount->promo_code_only) {
            return true;
        }

        // если суммируется со всеми остальными скидками
        if ($discount->summarizable_with_all) {
            return true;
        }

        /** @var Collection $discountIdsForBasketItem */
        $discountIdsForBasketItem = $this->basketItemsByDiscounts
            ->get($basketItem->get('id'))
            ->pluck('id');

        if (!$discount->isSynergyWithDiscounts($discountIdsForBasketItem)) {
            return false;
        }

        $synergyCondition = $discount->getSynergyCondition();

        if ($synergyCondition->getMaxValueType()) {
            $this->maxValueByDiscount[$discount->id] = [
                'value_type' => $synergyCondition->getMaxValueType(),
                'value' => $synergyCondition->getMaxValue(),
            ];
        }

        return true;
    }

    /**
     * @param int $basketItemId
     * @param Discount $discount
     * @param float $change
     * @return void
     */
    protected function addBasketItemByDiscount(int $basketItemId, Discount $discount, float $change): void
    {
        if (!$this->basketItemsByDiscounts->has($basketItemId)) {
            $this->basketItemsByDiscounts->put($basketItemId, collect());
        }

        $this->basketItemsByDiscounts[$basketItemId]->push([
            'id' => $discount->id,
            'change' => $change,
            'value' => $discount->value,
            'value_type' => $discount->value_type,
        ]);
    }

    /**
     * Содержит ли скидка условие по мерчанту, которое выполнилось
     * @param Discount $discount
     * @return bool
     */
    private function discountHasTrueMerchantCondition(Discount $discount): bool
    {
        return $this->getStoredMerchantConditions($discount)->isNotEmpty();
    }

    /**
     * Подходит ли под условие мерчанта basketItem
     * @param Discount $discount
     * @param Collection $basketItem
     * @return bool
     */
    private function basketItemSatisfiesMerchantCondition(Discount $discount, Collection $basketItem): bool
    {
        return $this->getStoredMerchantConditions($discount)
            ->filter(fn (DiscountCondition $condition) => in_array(
                $basketItem->get('merchant_id'),
                $condition->getMerchants()
            ))
            ->isNotEmpty();
    }

    /**
     * Получить сохраненные условия по мерчанту
     * в DiscountConditionStore данные записываются в MerchantConditionChecker
     * @param Discount $discount
     * @return Collection
     */
    private function getStoredMerchantConditions(Discount $discount): Collection
    {
        $conditionGroupIds = $discount->conditionGroups->pluck('id');

        return DiscountConditionStore::getConditions()->where(
            'type',
            DiscountCondition::MERCHANT
        )->filter(
            fn (DiscountCondition $condition) => $conditionGroupIds->contains($condition->discount_condition_group_id)
        );
    }
}
