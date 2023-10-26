<?php

namespace App\Services\Calculator\Discount\Checker\Resolvers;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\AbstractConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\CustomerConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\DeliveryMethodConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\DifferentProductsConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\EveryUnitProductConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\FalseConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\FirstOrderConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\MerchantConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\MinPriceBrandConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\MinPriceCategoryConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\MinPriceOrderConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\OrderSequenceNumberConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\PayMethodConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\RegionConditionChecker;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\TrueConditionChecker;
use App\Services\Calculator\Discount\Checker\Traits\WithExtraParams;

/**
 * Резолвит проверяющий класс для конкретного типа условия
 */
class DiscountConditionCheckerResolver
{
    use WithExtraParams;

    const TYPES = [
        DiscountCondition::FIRST_ORDER,
        DiscountCondition::MIN_PRICE_ORDER,
        DiscountCondition::MIN_PRICE_BRAND,
        DiscountCondition::MIN_PRICE_CATEGORY,
        DiscountCondition::EVERY_UNIT_PRODUCT,
        DiscountCondition::DELIVERY_METHOD,
        DiscountCondition::PAY_METHOD,
        DiscountCondition::REGION,
        DiscountCondition::CUSTOMER,
        DiscountCondition::ORDER_SEQUENCE_NUMBER,
        DiscountCondition::BUNDLE,
        DiscountCondition::DISCOUNT_SYNERGY,
        DiscountCondition::DIFFERENT_PRODUCTS_COUNT,
        DiscountCondition::MERCHANT,
    ];

    /**
     * @param int $type
     * @return AbstractConditionChecker
     */
    public function resolve(int $type): AbstractConditionChecker
    {
        $class = match($type) {
            DiscountCondition::FIRST_ORDER => FirstOrderConditionChecker::class,
            DiscountCondition::MIN_PRICE_ORDER => MinPriceOrderConditionChecker::class,
            DiscountCondition::MIN_PRICE_BRAND => MinPriceBrandConditionChecker::class,
            DiscountCondition::MIN_PRICE_CATEGORY => MinPriceCategoryConditionChecker::class,
            DiscountCondition::EVERY_UNIT_PRODUCT => EveryUnitProductConditionChecker::class,
            DiscountCondition::PAY_METHOD => PayMethodConditionChecker::class,
            DiscountCondition::REGION => RegionConditionChecker::class,
            DiscountCondition::MERCHANT => MerchantConditionChecker::class,
            DiscountCondition::CUSTOMER => CustomerConditionChecker::class,
            DiscountCondition::ORDER_SEQUENCE_NUMBER => OrderSequenceNumberConditionChecker::class,
            DiscountCondition::DIFFERENT_PRODUCTS_COUNT => DifferentProductsConditionChecker::class,
            DiscountCondition::DELIVERY_METHOD => DeliveryMethodConditionChecker::class,
            //проверяются отдельно потом или не реализовано
            DiscountCondition::BUNDLE,
            DiscountCondition::DISCOUNT_SYNERGY => TrueConditionChecker::class,
            default => FalseConditionChecker::class
        };

        return new $class();
    }
}
