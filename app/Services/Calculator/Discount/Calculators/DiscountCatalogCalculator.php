<?php

namespace App\Services\Calculator\Discount\Calculators;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\Resolvers\DiscountConditionCheckerResolver;
use App\Services\Calculator\Discount\DiscountFetcher;

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

    /**
     * @return void
     */
    protected function fetchDiscounts(): void
    {
        $discountFetcher = new DiscountFetcher($this->input);
        $this->discounts = $discountFetcher->getDiscounts(self::DISCOUNT_TYPES_OF_CATALOG);
    }

    /**
     * Исключить все, кроме DISCOUNT_SYNERGY и MERCHANT
     * @return array
     */
    protected function getExceptingConditionTypes(): array
    {
        return array_diff(
            DiscountConditionCheckerResolver::TYPES,
            [
                DiscountCondition::DISCOUNT_SYNERGY,
                DiscountCondition::MERCHANT,
            ]
        );
    }
}
