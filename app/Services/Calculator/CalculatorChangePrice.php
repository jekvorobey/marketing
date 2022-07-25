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
     * Возвращает размер скидки (без учета предыдущих скидок)
     *
     * @param OfferDto|array $item - оффер или доставка (если array)
     * @param int $lowestPossiblePrice Самая низкая возможная цена (по умолчанию = 1 рубль)
     */
    public function changePrice(
        $item,
        int $value,
        int $valueType = Discount::DISCOUNT_VALUE_TYPE_RUB,
        int $lowestPossiblePrice = self::LOWEST_POSSIBLE_PRICE,
        ?Discount $discount = null
    ): array {
        if (!isset($item['price']) || $value <= 0) {
            return ['discountValue' => 0];
        }

        if ($item instanceof OfferDto && !$item->product_id) {
            $lowestPossiblePrice = self::LOWEST_MASTERCLASS_PRICE;
        }

        return $discount && $discount->type === Discount::DISCOUNT_TYPE_BUNDLE_OFFER
            ? $this->processBundleType($item, $value, $discount, $valueType, $lowestPossiblePrice)
            : $this->processAllTypes($item, $value, $valueType, $lowestPossiblePrice);
    }

    /**
     * Получить размер скидки и новую цену для бандла
     */
    private function processBundleType(
        OfferDto|array $item,
        int $value,
        Discount $discount,
        int $valueType = Discount::DISCOUNT_VALUE_TYPE_RUB,
        int $lowestPossiblePrice = self::LOWEST_POSSIBLE_PRICE
    ): array {
        $result = [];
        if ($item['bundle_id'] !== $discount->id) {
            return ['discountValue' => 0];
        }

        $result['price'] ??= $item['price'];

        $currentDiscount = $item['discount'] ?? 0;
        $currentCost = $item['cost'] ?? $item['price'];
        $discountValue = $this->getDiscountValue($result['price'], $currentCost, $value, $valueType, $lowestPossiblePrice);

        $result['discount'] = $currentDiscount + $discountValue;
        // Конечная цена товара в бандле всегда округляется до целого
        $result['price'] = self::round($currentCost - $result['discount'], self::FLOOR);
        $result['cost'] = $currentCost;

        $result['discountValue'] = $discountValue;

        return $result;
    }

    /**
     * Получить размер скидки и новую цену для офферов
     */
    private function processAllTypes(
        OfferDto|array $item,
        int $value,
        int $valueType = Discount::DISCOUNT_VALUE_TYPE_RUB,
        int $lowestPossiblePrice = self::LOWEST_POSSIBLE_PRICE
    ): array {
        $currentDiscount = $item['discount'] ?? 0;
        $currentCost = $item['cost'] ?? $item['price'];
        $discountValue = $this->getDiscountValue($item['price'], $currentCost, $value, $valueType, $lowestPossiblePrice);

        $result['discount'] = $currentDiscount + $discountValue;
        $result['price'] = round($currentCost - $result['discount'], 2);
        $result['cost'] = $currentCost;

        $result['discountValue'] = $discountValue;

        return $result;
    }

    private function getDiscountValue(int $price, int $currentCost, int $value, $valueType, $lowestPossiblePrice): float
    {
        $discountValue = min($price, $this->calculateDiscountByType($currentCost, $value, $valueType));

        /** Цена не может быть меньше $lowestPossiblePrice */
        if ($price - $discountValue < $lowestPossiblePrice) {
            $discountValue = $price - $lowestPossiblePrice;
        }

        return $discountValue;
    }

    /**
     * Рассчитать процент от значения и округлить указанным методом.
     * @param float|int $value - значение от которого берётся процент
     * @param float|int $percent - процент (0-100)
     */
    public static function percent(float|int $value, float|int $percent, int $method = self::FLOOR): int
    {
        return self::round($value * $percent / 100, $method);
    }

    /**
     * Округлить значение указанным способом.
     */
    public static function round($value, $method = self::FLOOR): int
    {
        return match ($method) {
            self::FLOOR => (int) floor($value),
            self::CEIL => (int) ceil($value),
            default => (int) round($value),
        };
    }

    public function calculateDiscountByType($cost, $value, $valueType): float
    {
        return match ($valueType) {
            Discount::DISCOUNT_VALUE_TYPE_PERCENT => round($cost * $value / 100, 2),
            Discount::DISCOUNT_VALUE_TYPE_RUB => $value,
            default => 0,
        };
    }

    public function syncItemWithChangedPrice(OfferDto|array $item, array $changedPrice): OfferDto|array
    {
        if (isset($changedPrice['discount'])) {
            $item['discount'] = $changedPrice['discount'];
        }
        if (isset($changedPrice['price'])) {
            $item['price'] = $changedPrice['price'];
        }
        if (isset($changedPrice['cost'])) {
            $item['cost'] = $changedPrice['cost'];
        }

        return $item;
    }
}
