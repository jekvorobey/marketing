<?php

namespace App\Services\Calculator\Discount;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountOffer;
use Illuminate\Support\Collection;

class DiscountCatalogCalculator extends DiscountCalculator
{
    public const DISCOUNT_TYPES_OF_CATALOG = [
        Discount::DISCOUNT_TYPE_OFFER,
        Discount::DISCOUNT_TYPE_ANY_OFFER,
        Discount::DISCOUNT_TYPE_BRAND,
        Discount::DISCOUNT_TYPE_ANY_BRAND,
        Discount::DISCOUNT_TYPE_CATEGORY,
        Discount::DISCOUNT_TYPE_ANY_CATEGORY,
        Discount::DISCOUNT_TYPE_MASTERCLASS,
        Discount::DISCOUNT_TYPE_ANY_MASTERCLASS,
    ];

    protected function fetchDiscounts(): void
    {
        $discountFetcher = new DiscountFetcher($this->input);
        $this->discounts = $discountFetcher->getDiscounts(self::DISCOUNT_TYPES_OF_CATALOG);
    }

    /**
     * Проверяет доступность применения скидки на все соответствующие условия
     *
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
                    break 2;
                default:
                    return false;
            }
        }

        return true;
    }
}
