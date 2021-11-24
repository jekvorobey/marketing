<?php

namespace App\Services\Calculator;

use App\Models\Discount\Discount;
use Pim\Dto\Offer\OfferDto;

class CalculatorChangePrice
{
    /** Самая низкая возможная цена (1 рубль) */
    public const LOWEST_POSSIBLE_PRICE = 1;

    /** Наименьшая возможная цена на мастер-класс */
    public const LOWEST_MASTERCLASS_PRICE = 0;

    /** Цена для бесплатной доставки */
    public const FREE_DELIVERY_PRICE = 0;

    public const FLOOR = 1;
    public const CEIL = 2;
    public const ROUND = 3;

    /**
     * Рассчитать процент от значения и округлить указанным методом.
     * @param int|float $value - значение от которого берётся процент
     * @param int|float $percent - процент (0-100)
     * @param int $method - способ округления (например self::FLOOR)
     *
     * @return int
     */
    public static function percent($value, $percent, $method = self::FLOOR)
    {
        return self::round($value * $percent / 100, $method);
    }

    /**
     * Округлить значение указанным способом.
     * @param $value
     * @param int $method
     *
     * @return int
     */
    public static function round($value, $method = self::FLOOR)
    {
        switch ($method) {
            case self::FLOOR:
                return (int) floor($value);
            case self::CEIL:
                return (int) ceil($value);
            default:
                return (int) round($value);
        }
    }

    /**
     * Возвращает размер скидки (без учета предыдущих скидок)
     *
     * @param OfferDto|array $item - оффер или доставка (если array)
     * @param $value
     * @param int $valueType
     * @param int $lowestPossiblePrice Самая низкая возможная цена (по умолчанию = 1 рубль)
     */
    public function changePrice(
        $item,
        $value,
        $valueType = Discount::DISCOUNT_VALUE_TYPE_RUB,
        int $lowestPossiblePrice = self::LOWEST_POSSIBLE_PRICE,
        ?Discount $discount = null
    ): array {
        $result = [];
        if (!isset($item['price']) || $value <= 0) {
            return ['discountValue' => 0];
        }

        if ($item instanceof OfferDto && !$item->product_id) {
            $lowestPossiblePrice = self::LOWEST_MASTERCLASS_PRICE;
        }

        if ($discount && $discount->type == Discount::DISCOUNT_TYPE_BUNDLE_OFFER) {
            if ($item['bundles']->has($discount->id)) {
                $offerInBundle = &$item['bundles'][$discount->id];
            } else {
                return ['discountValue' => 0];
            }

            $offerInBundle['price'] ??= $item['price'];

            $currentDiscount = $item['discount'] ?? 0;
            $currentCost = $item['cost'] ?? $item['price'];
            $discountValue = min($offerInBundle['price'], $this->calculateDiscountByType($currentCost, $value, $valueType));

            /** Цена не может быть меньше $lowestPossiblePrice */
            if ($offerInBundle['price'] - $discountValue < $lowestPossiblePrice) {
                $discountValue = $offerInBundle['price'] - $lowestPossiblePrice;
            }

            # Конечная цена товара в бандле всегда округляется до целого
            $offerInBundle['discount'] = $currentDiscount + $discountValue;
            $offerInBundle['price'] = self::round($currentCost - $offerInBundle['discount'], self::ROUND);
            $offerInBundle['cost'] = $currentCost;
            $result['cost'] = $currentCost;

            $result['discountValue'] = $discountValue;
        } else {
            $currentDiscount = $item['discount'] ?? 0;
            $currentCost = $item['cost'] ?? $item['price'];
            $discountValue = min($item['price'], $this->calculateDiscountByType($currentCost, $value, $valueType));

            /** Цена не может быть меньше $lowestPossiblePrice */
            if ($item['price'] - $discountValue < $lowestPossiblePrice) {
                $discountValue = $item['price'] - $lowestPossiblePrice;
            }

            $result['discount'] = $currentDiscount + $discountValue;
            $result['price'] = round($currentCost - $result['discount'], 2);
            $result['cost'] = $currentCost;

            $result['discountValue'] = $discountValue;
        }

        return $result;
    }

    /**
     * @param $cost
     * @param $value
     * @param $valueType
     *
     * @return float
     */
    public function calculateDiscountByType($cost, $value, $valueType)
    {
        switch ($valueType) {
            case Discount::DISCOUNT_VALUE_TYPE_PERCENT:
                return round($cost * $value / 100, 2);
            case Discount::DISCOUNT_VALUE_TYPE_RUB:
                return $value;
            default:
                return 0;
        }
    }
}
