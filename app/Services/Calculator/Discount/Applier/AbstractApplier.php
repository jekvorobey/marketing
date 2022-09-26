<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\InputCalculator;
use Illuminate\Support\Collection;

abstract class AbstractApplier
{
    /**
     * Если по какой-то скидке есть ограничение на максимальный итоговый размер скидки по офферу
     * @var array [discount_id => ['value' => value, 'value_type' => value_type], ...]
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

    abstract public function apply(Discount $discount): ?float;

    public function getModifiedBasketItemsByDiscounts(): Collection
    {
        return $this->basketItemsByDiscounts;
    }

    public function getModifiedInputBasketItems(): Collection
    {
        return $this->input->basketItems;
    }

    /**
     * Можно ли применить скидку к элементу корзины
     */
    protected function applicableToBasketItem(Discount $discount, $basketItemId): bool
    {
        if ($this->appliedDiscounts->isEmpty() || !$this->basketItemsByDiscounts->has($basketItemId)) {
            return true;
        }

        /** @var Collection $discountIdsForBasketItem */
        $discountIdsForBasketItem = $this->basketItemsByDiscounts[$basketItemId]->pluck('id');

        /** @var DiscountCondition $synergyCondition */
        $synergyCondition = $discount->conditions->firstWhere('type', DiscountCondition::DISCOUNT_SYNERGY);
        if (!$synergyCondition) {
            return false;
        }

        $synergyDiscountIds = $synergyCondition->getSynergy();
        if ($discountIdsForBasketItem->intersect($synergyDiscountIds)->count() !== $discountIdsForBasketItem->count()) {
            return false;
        }

        if ($synergyCondition->getMaxValueType()) {
            $this->maxValueByDiscount[$discount->id] = [
                'value_type' => $synergyCondition->getMaxValueType(),
                'value' => $synergyCondition->getMaxValue(),
            ];
        }

        return true;
    }

    /**
     * @param float|null $appliedDiscountPercentValue если применился другой % (при суммировании скидок с ограничением в %), отличный от % в скидке, то сохраняем новый %
     */
    protected function addBasketItemByDiscount(
        int $basketItemId,
        Discount $discount,
        float $change
    ): void {
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
}
