<?php

namespace App\Services\Calculator;

use App\Models\Discount\Discount;
use Illuminate\Support\Collection;

abstract class AbstractCalculator
{
    /** @var int Цена для бесплатной доставки */
    const FREE_DELIVERY_PRICE = 0;

    /** @var int Самая низкая возможная цена (1 рубль) */
    const LOWEST_POSSIBLE_PRICE = 1;

    /** @var int Максимально возомжная скидка в процентах */
    const HIGHEST_POSSIBLE_PRICE_PERCENT = 100;

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

    public function __construct(InputCalculator $inputCalculator, OutputCalculator $outputCalculator)
    {
        $this->input  = $inputCalculator;
        $this->output = $outputCalculator;
    }

    /**
     * Возвращает размер скидки (без учета предыдущих скидок)
     *
     * @param      $item
     * @param      $value
     * @param int  $valueType
     * @param bool $apply               нужно ли применять скидку
     * @param int  $lowestPossiblePrice Самая низкая возможная цена (по умолчанию = 1 рубль)
     * @param Discount  $discountType
     *
     * @return int
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

        if ($discount->type == Discount::DISCOUNT_TYPE_BUNDLE_OFFER) {
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

            if ($apply) {
                $offerInBundle['discount'] = $currentDiscount + $discountValue;
                $offerInBundle['price']    = $currentCost - $offerInBundle['discount'];
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
                $item['price']    = $currentCost - $item['discount'];
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
     * @return int
     */
    protected function calculateDiscountByType($cost, $value, $valueType)
    {
        switch ($valueType) {
            case Discount::DISCOUNT_VALUE_TYPE_PERCENT:
                return round($cost * $value / 100);
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
}
