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
    protected function applicableToBasketItem(Discount $discount, Collection $basketItem): bool
    {
        $merchantCondition = $discount->conditions->firstWhere('type', DiscountCondition::MERCHANT);

        if ($merchantCondition && !in_array($basketItem->get('merchant_id'), $merchantCondition->getMerchants())) {
            return false;
        }

        if ($this->appliedDiscounts->isEmpty() || !$this->basketItemsByDiscounts->has($basketItem->get('id'))) {
            return true;
        }

        //если суммируется со всеми остальными скидками
        if ($discount->summarizable_with_all) {
            return true;
        }

        /** @var Collection $discountIdsForBasketItem */
        $discountIdsForBasketItem = $this->basketItemsByDiscounts[$basketItem->get('id')]->pluck('id');

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
}
