<?php

namespace App\Services\Calculator;

use App\Models\Discount\Discount;
use App\Models\Option\Option;
use Illuminate\Support\Collection;
use Pim\Dto\Offer\OfferDto;

abstract class AbstractCalculator
{
    public const FLOOR = 1;
    public const CEIL = 2;
    public const ROUND = 3;

    /** @var int Цена для бесплатной доставки */
    public const FREE_DELIVERY_PRICE = 0;
    /** @var int Самая низкая возможная цена (1 рубль) */
    public const LOWEST_POSSIBLE_PRICE = 1;
    /** @var int Наименьшая возможная цена на мастер-класс */
    public const LOWEST_MASTERCLASS_PRICE = 0;
    /** @var int Максимально возможная скидка в процентах */
    public const HIGHEST_POSSIBLE_PRICE_PERCENT = 100;
    /** @var int отношение бонуса к рублю */
    public const DEFAULT_BONUS_PER_RUBLES = 1;
    /** @var int сколько процентов стоимости товара можно оплатить бонусами */
    public const DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT = 100;
    /** @var int сколько процентов стоимости товара со скидкой можно оплатить бонусами */
    public const DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_DISCOUNT_PRODUCT = 100;
    /** @var int сколько процентов стоимости заказа можно оплатить бонусами */
    public const DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_ORDER = 100;

    /**
     * Входные условия, влияющие на получения скидки
     * @var InputCalculator
     */
    protected $input;

    /**
     * Выходные данные калькулятора
     * @var OutputCalculator
     */
    protected $output;

    private $options;

    public function __construct(InputCalculator $inputCalculator, OutputCalculator $outputCalculator)
    {
        $this->input  = $inputCalculator;
        $this->output = $outputCalculator;
    }

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
                return (int)floor($value);
            case self::CEIL:
                return (int)ceil($value);
            default:
                return (int)round($value);
        }
    }

    /**
     * Возвращает размер скидки (без учета предыдущих скидок)
     *
     * @param      OfferDto|array $item - оффер или доставка (если array)
     * @param      $value
     * @param int  $valueType
     * @param bool $apply               нужно ли применять скидку
     * @param int  $lowestPossiblePrice Самая низкая возможная цена (по умолчанию = 1 рубль)
     * @param Discount  $discount
     *
     * @return float
     */
    protected function changePrice(
        &$item,
        $value,
        $valueType = Discount::DISCOUNT_VALUE_TYPE_RUB,
        $apply = true,
        int $lowestPossiblePrice = self::LOWEST_POSSIBLE_PRICE,
        Discount $discount = null
    ) {
        if (!isset($item['price']) || $value <= 0) {
            return 0;
        }

        if ($item instanceof OfferDto && !$item->product_id) {
            $lowestPossiblePrice = self::LOWEST_MASTERCLASS_PRICE;
        }

        if ($discount && $discount->type == Discount::DISCOUNT_TYPE_BUNDLE_OFFER) {
            if ($item['bundles']->has($discount->id)) {
                $offerInBundle = &$item['bundles'][$discount->id];
            } else {
                return 0;
            }

            $offerInBundle['price'] = isset($offerInBundle['price']) ?
                $offerInBundle['price'] :
                $item['price'];

            $currentDiscount = $item['discount'] ?? 0;
            $currentCost     = $item['cost'] ?? $item['price'];
            $discountValue   = min($offerInBundle['price'], $this->calculateDiscountByType($currentCost, $value, $valueType));

            /** Цена не может быть меньше $lowestPossiblePrice */
            if ($offerInBundle['price'] - $discountValue < $lowestPossiblePrice) {
                $discountValue = $offerInBundle['price'] - $lowestPossiblePrice;
            }

            # Конечная цена товара в бандле всегда округляется до целого
            if ($apply) {
                $offerInBundle['discount'] = $currentDiscount + $discountValue;
                $offerInBundle['price']    = self::round($currentCost - $offerInBundle['discount'], self::ROUND);
                $offerInBundle['cost'] = $currentCost;
                $item['cost'] = $currentCost;
            }
        } else {
            $currentDiscount = $item['discount'] ?? 0;
            $currentCost     = $item['cost'] ?? $item['price'];
            $discountValue   = min($item['price'], $this->calculateDiscountByType($currentCost, $value, $valueType));

            /** Цена не может быть меньше $lowestPossiblePrice */
            if ($item['price'] - $discountValue < $lowestPossiblePrice) {
                $discountValue = $item['price'] - $lowestPossiblePrice;
            }

            if ($apply) {
                $item['discount'] = $currentDiscount + $discountValue;
                $item['price']    = round($currentCost - $item['discount'], 2);
                $item['cost']     = $currentCost;
            }
        }

        return $discountValue;
    }

    /**
     * @param $cost
     * @param $value
     * @param $valueType
     *
     * @return float
     */
    protected function calculateDiscountByType($cost, $value, $valueType)
    {
        switch ($valueType) {
            case Discount::DISCOUNT_VALUE_TYPE_PERCENT:
                return round($cost * $value / 100,2);
            case Discount::DISCOUNT_VALUE_TYPE_RUB:
                return $value;
            default:
                return 0;
        }
    }

    /**
     * @param $brandIds
     * @param $exceptOfferIds
     * @param $merchantId
     *
     * @return Collection
     */
    protected function filterForBrand($brandIds, $exceptOfferIds, $merchantId)
    {
        return $this->input->offers->filter(function ($offer) use ($brandIds, $exceptOfferIds, $merchantId) {
            return ($brandIds->search($offer['brand_id']) !== false)
                && ($exceptOfferIds->search($offer['id']) === false)
                && (!$merchantId || $offer['merchant_id'] == $merchantId);
        })->pluck('id');
    }

    /**
     * @param $categoryIds
     * @param $exceptBrandIds
     * @param $exceptOfferIds
     * @param $merchantId
     *
     * @return Collection
     */
    protected function filterForCategory($categoryIds, $exceptBrandIds, $exceptOfferIds, $merchantId)
    {
        return $this->input->offers->filter(function ($offer) use (
            $categoryIds,
            $exceptBrandIds,
            $exceptOfferIds,
            $merchantId
        ) {
            $categories    = InputCalculator::getAllCategories();
            $offerCategory = $categories->has($offer['category_id'])
                ? $categories[$offer['category_id']]
                : null;

            return $offerCategory
                && $categoryIds->reduce(function ($carry, $categoryId) use ($offerCategory, $categories) {
                    return $carry ||
                        (
                            $categories->has($categoryId)
                            && $categories[$categoryId]->isSelfOrAncestorOf($offerCategory)
                        );
                })
                && $exceptBrandIds->search($offer['brand_id']) === false
                && $exceptOfferIds->search($offer['id']) === false
                && (!$merchantId || $offer['merchant_id'] == $merchantId);
        })->pluck('id');
    }

    /**
     * Получить опцию по ключу (с кэшем в рамках процесса)
     * @param mixed $key
     * @return mixed
     */
    protected function getOption($key)
    {
        $this->loadOptions();
        return $this->options[$key] ?? null;
    }

    /**
     * Загрузить опции из БД (не грузит повторно)
     */
    private function loadOptions()
    {
        if ($this->options) {
            return;
        }
        /** @var Option[] $rawOptions */
        $rawOptions = Option::query()->get();
        $options = [];
        foreach ($rawOptions as $option) {
            $options[$option->key] = $option->value['value'];
        }
        $options[Option::KEY_BONUS_PER_RUBLES] = $options[Option::KEY_BONUS_PER_RUBLES]
            ?? self::DEFAULT_BONUS_PER_RUBLES;
        $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT] = $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT]
            ?? self::DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT;
        $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_DISCOUNT_PRODUCT] = $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_DISCOUNT_PRODUCT]
            ?? self::DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_DISCOUNT_PRODUCT;
        $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER] = $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER]
            ?? self::DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_ORDER;
        $this->options = $options;
    }

}
