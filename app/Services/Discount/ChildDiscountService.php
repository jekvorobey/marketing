<?php

namespace App\Services\Discount;

use App\Models\Discount\Discount;
use Pim\Core\PimException;

/**
 * Класс для создания дочерних скидок и их обновления
 *
 * @property $parentDiscountData - данные скидки-родителя (из request'а)
 * @property $childrenRawList - массив данных дочерних скидок (из request'а)
 */
class ChildDiscountService
{
    protected Discount $discount;
    protected array $parentDiscountData;
    protected array $childrenRawList;

    /**
     * Создать дочерние скидки для скидки
     * @param Discount $discount
     * @param array $parentDiscountData
     * @return void
     * @throws PimException
     */
    public function createChildDiscounts(Discount $discount, array $parentDiscountData): void
    {
        if ($discount->type != Discount::DISCOUNT_TYPE_MULTI) {
            return;
        }

        $this->discount = $discount;
        $this->parentDiscountData = $parentDiscountData;
        $this->childrenRawList = $parentDiscountData['child_discounts'] ?? [];

        foreach ($this->childrenRawList as $childRawData) {
            $this->createChildDiscount($childRawData);
        }
    }

    /**
     * Обновить дочерние скидки для скидки
     * @param Discount $discount
     * @param array $parentDiscountData
     * @return void
     * @throws PimException
     */
    public function updateChildDiscounts(Discount $discount, array $parentDiscountData): void
    {
        if ($discount->type != Discount::DISCOUNT_TYPE_MULTI) {
            return;
        }

        $this->discount = $discount;
        $this->parentDiscountData = $parentDiscountData;
        $this->childrenRawList = $parentDiscountData['child_discounts'] ?? [];

        $old = collect($this->childrenRawList)->whereNotNull('id');
        $new = collect($this->childrenRawList)->whereNull('id');

        $this->discount
            ->childDiscounts()
            ->whereNotIn('id', $old->pluck('id'))
            ->delete();

        foreach ($old as $childDiscount) {
            $this->updateChildDiscount($childDiscount);
        }

        foreach ($new as $childDiscount) {
            $this->createChildDiscount($childDiscount);
        }
    }

    /**
     * Создать дочернюю скидку
     * @param array $childRawData
     * @return void
     * @throws PimException
     */
    protected function createChildDiscount(array $childRawData): void
    {
        foreach ($this->getParentDiscountKeys() as $key) {
            $childRawData[$key] = $this->parentDiscountData[$key];
        }

        $childRawData['name'] = "{$this->parentDiscountData['name']}_" . $this->discount->childDiscounts()->count() + 1;
        $childRawData['parent_discount_id'] = $this->discount->id;
        $childRawData['relations'] = $this->prepareRelations($childRawData);

        DiscountHelper::create($childRawData);
    }

    /**
     * Обновить дочернюю скидку
     * @param array $childRawData
     * @return void
     */
    protected function updateChildDiscount(array $childRawData): void
    {
        $localDiscount = $this->discount->childDiscounts->firstWhere('id', $childRawData['id']);

        foreach ($this->getParentDiscountKeys() as $key) {
            $localDiscount[$key] = $this->parentDiscountData[$key];
        }

        foreach ($this->getChildDiscountKeys() as $key) {
            $localDiscount[$key] = $childRawData[$key];
        }

        $relations = $this->prepareRelations($childRawData);
        DiscountHelper::updateRelations($localDiscount, $relations);

        $localDiscount->save();

        if ($this->parentDiscountData['promo_code_only'] && is_array($this->parentDiscountData['promoCodes'])) {
            $localDiscount->promoCodes()->sync($this->parentDiscountData['promoCodes']);
        } else {
            $localDiscount->promoCodes()->detach();
        }
    }

    /**
     * Ключи, которые передаются дочерней скидке от родительской
     * @return array
     */
    protected function getParentDiscountKeys(): array
    {
        $except = ['child_discounts', 'relations', 'promoCodes'];
        return array_diff(array_keys($this->parentDiscountData), $except, $this->getChildDiscountKeys());
    }

    /**
     * Ключи дочерней скидки (не передаются от родительской)
     * @return string[]
     */
    protected function getChildDiscountKeys(): array
    {
        return ['type', 'value', 'value_type', 'name'];
    }

    /**
     * Подготовить отношения скидки под формат для обработки DiscountHelper'ом
     * @param array $childRawData
     * @return array
     */
    protected function prepareRelations(array $childRawData): array
    {
        $relations = $this->parentDiscountData['relations'] ?? [];

        switch ($childRawData['type']) {
            case Discount::DISCOUNT_TYPE_BRAND:
                $relations[Discount::DISCOUNT_BRAND_RELATION] = array_map(
                    fn (int $brandId) => ['except' => false, 'brand_id' => $brandId],
                    $childRawData['brands']
                );
                break;
            case Discount::DISCOUNT_TYPE_CATEGORY:
                $relations[Discount::DISCOUNT_CATEGORY_RELATION] = array_map(
                    fn (int $categoryId) => ['except' => false, 'category_id' => $categoryId],
                    $childRawData['categories']
                );
                break;
            case Discount::DISCOUNT_TYPE_OFFER:
                $relations[Discount::DISCOUNT_OFFER_RELATION] = array_map(
                    fn (int $offerId) => ['except' => false, 'offer_id' => $offerId],
                    explode(',', $childRawData['offers'])
                );
                break;
        }

        if (isset($childRawData['except']['offers'])) {
            $relations[Discount::DISCOUNT_OFFER_RELATION] = array_map(
                fn (int $offerId) => ['except' => true, 'offer_id' => $offerId],
                $childRawData['except']['offers']
            );
        }

        if (isset($childRawData['merchants'])) {
            $relations[Discount::DISCOUNT_MERCHANT_RELATION] = array_map(
                fn (int $merchantId) => ['except' => true, 'merchant_id' => $merchantId],
                $childRawData['merchants']
            );
        }

        return $relations;
    }
}
