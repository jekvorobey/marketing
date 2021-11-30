<?php

namespace App\Services\Calculator\Discount;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition as DiscountConditionModel;

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
     * @inheritDoc
     */
    protected function getExcludedConditions(): array
    {
        return [
            DiscountConditionModel::FIRST_ORDER,
            DiscountConditionModel::MIN_PRICE_ORDER,
            DiscountConditionModel::MIN_PRICE_BRAND,
            DiscountConditionModel::MIN_PRICE_CATEGORY,
            DiscountConditionModel::EVERY_UNIT_PRODUCT,
            DiscountConditionModel::DELIVERY_METHOD,
            DiscountConditionModel::PAY_METHOD,
            DiscountConditionModel::REGION,
            DiscountConditionModel::CUSTOMER,
            DiscountConditionModel::ORDER_SEQUENCE_NUMBER,
            DiscountConditionModel::BUNDLE,
        ];
    }
}
