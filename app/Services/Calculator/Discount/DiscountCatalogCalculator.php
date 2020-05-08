<?php

namespace App\Services\Calculator\Discount;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountOffer;
use Illuminate\Support\Collection;

class DiscountCatalogCalculator extends DiscountCalculator
{
    /**
     * Получаем все возможные скидки и офферы из DiscountOffer
     * @return $this
     */
    protected function fetchDiscountOffers()
    {
        $this->relations['offers'] = DiscountOffer::select(['discount_id', 'offer_id', 'except'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->get()
            ->filter(function ($discountOffer) {
                return $this->input->offers->has($discountOffer['offer_id']);
            })
            ->groupBy('discount_id');
        return $this;
    }

    /**
     * Получаем все возможные скидки и бренды из DiscountBrand
     * @return $this
     */
    protected function fetchDiscountBrands()
    {
        $this->relations['brands'] = DiscountBrand::select(['discount_id', 'brand_id', 'except'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->get()
            ->filter(function ($discountBrand) {
                return $this->input->brands->has($discountBrand['brand_id']);
            })
            ->groupBy('discount_id');
        return $this;
    }

    /**
     * Получаем все возможные скидки и условия из DiscountCondition
     * @param Collection $discountIds
     * @return $this
     */
    protected function fetchDiscountConditions(Collection $discountIds)
    {
        $this->relations['conditions'] = DiscountCondition::select(['discount_id', 'type', 'condition'])
            ->whereIn('discount_id', $discountIds)
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Можно ли применить данную скидку (независимо от других скидок)
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkDiscount(Discount $discount): bool
    {
        return $this->checkType($discount)
            && $this->checkCustomerRole($discount)
            && $this->checkSegment($discount);
    }

    /**
     * Проверяет доступность применения скидки на все соответствующие условия
     *
     * @param Collection $conditions
     * @return bool
     * @todo
     */
    protected function checkConditions(Collection $conditions): bool
    {
        /** @var DiscountCondition $condition */
        foreach ($conditions as $condition) {
            switch ($condition->type) {
                /**
                 * Оставляем только скидки, у которых отсутсвуют доп. условия (считаются в корзине или чекауте).
                 */
                case DiscountCondition::DISCOUNT_SYNERGY:
                    continue(2);
                default:
                    return false;
            }
        }

        return true;
    }

    /**
     * Получить все активные скидки, которые могут быть показаны (рассчитаны) в каталоге
     *
     * @return $this
     */
    protected function fetchActiveDiscounts()
    {
        $this->discounts = Discount::select(['id', 'type', 'value', 'value_type', 'promo_code_only', 'merchant_id'])
            ->showInCatalog()
            ->orderBy('type')
            ->get();

        return $this;
    }
}
