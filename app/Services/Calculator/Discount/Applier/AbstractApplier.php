<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\DiscountConditionStore;
use App\Services\Calculator\InputCalculator;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Support\Collection;
use Pim\Core\PimException;
use Pim\Dto\Product\ProductDto;
use Pim\Dto\Product\ProductPropertyValueDto;
use Pim\Services\ProductService\ProductService;

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
    protected Collection $basketProducts;

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
        if (!$this->checkStoredDiscountConditions($discount, $basketItem)) {
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
     * Поверить сохраненные в сторе условия скидки.
     * Сохраняются туда на этапе проверки условий.
     * @param Discount $discount
     * @param Collection $basketItem
     * @return bool
     */
    protected function checkStoredDiscountConditions(Discount $discount, Collection $basketItem): bool
    {
        if ($this->discountHasTrueMerchantCondition($discount) &&
            !$this->basketItemSatisfiesMerchantCondition($discount, $basketItem)) {
            return false;
        }

        if ($this->discountHasTruePropertyCondition($discount) &&
            !$this->basketItemSatisfiesPropertyCondition($discount, $basketItem)) {
            return false;
        }

        return true;
    }

    /**
     * Содержит ли скидка условие по мерчанту, которое выполнилось
     * @param Discount $discount
     * @return bool
     */
    protected function discountHasTrueMerchantCondition(Discount $discount): bool
    {
        return $this->getStoredConditions($discount, DiscountCondition::MERCHANT)->isNotEmpty();
    }

    /**
     * Содержит ли скидка условие по характеристике, которое выполнилось
     * @param Discount $discount
     * @return bool
     */
    protected function discountHasTruePropertyCondition(Discount $discount): bool
    {
        return $this->getStoredConditions($discount, DiscountCondition::PROPERTY)->isNotEmpty();
    }

    /**
     * Подходит ли под условие мерчанта basketItem
     * @param Discount $discount
     * @param Collection $basketItem
     * @return bool
     */
    protected function basketItemSatisfiesMerchantCondition(Discount $discount, Collection $basketItem): bool
    {
        return $this->getStoredConditions($discount, DiscountCondition::MERCHANT)
            ->filter(fn (DiscountCondition $condition) => in_array(
                $basketItem->get('merchant_id'),
                $condition->getMerchants()
            ))
            ->isNotEmpty();
    }

    /**
     * Подходит ли под условие характеристики basketItem
     * @param Discount $discount
     * @param Collection $basketItem
     * @return bool
     */
    protected function basketItemSatisfiesPropertyCondition(Discount $discount, Collection $basketItem): bool
    {
        $product = $this->getBasketProducts()->get($basketItem->get('product_id'));

        return $product && $this->getStoredConditions($discount, DiscountCondition::PROPERTY)
            ->filter(function (DiscountCondition $condition) use ($product) {
                return collect($product->properties)->contains(
                        fn (ProductPropertyValueDto $dto) => $dto->property_id == $condition->getProperty() &&
                            in_array($dto->value, $condition->getPropertyValues())
                    );
            })
            ->isNotEmpty();
    }

    /**
     * Получить сохраненные условия
     * @param Discount $discount
     * @param int $type
     * @return Collection
     */
    protected function getStoredConditions(Discount $discount, int $type): Collection
    {
        $conditionGroupIds = $discount->conditionGroups->pluck('id');
        return DiscountConditionStore::getByType($type)->filter(
            fn (DiscountCondition $condition) => $conditionGroupIds->contains($condition->discount_condition_group_id)
        );
    }

    /**
     * Получить товары корзины
     * @return Collection [[id => product],... ]
     */
    protected function getBasketProducts(): Collection
    {
        try {
            if (!isset($this->basketProducts)) {
                $this->basketProducts = app(ProductService::class)->products(
                    (new RestQuery())
                        ->addFields(ProductDto::entity(), 'id', 'properties')
                        ->include('properties')
                        ->setFilter('id', $this->input->basketItems->pluck('product_id')->toArray())
                )->keyBy('id');
            }

            return $this->basketProducts;
        } catch (PimException $e) {
            report($e);
            return new Collection();
        }
    }
}
