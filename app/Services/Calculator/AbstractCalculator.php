<?php

namespace App\Services\Calculator;

use App\Models\Option\Option;
use Illuminate\Support\Collection;

abstract class AbstractCalculator
{
    /** Максимально возможная скидка в процентах */
    public const HIGHEST_POSSIBLE_PRICE_PERCENT = 100;

    /** отношение бонуса к рублю */
    public const DEFAULT_BONUS_PER_RUBLES = 1;

    /** сколько процентов стоимости товара можно оплатить бонусами */
    public const DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT = 100;

    /** сколько процентов стоимости товара со скидкой можно оплатить бонусами */
    public const DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_DISCOUNT_PRODUCT = 100;

    /** сколько процентов стоимости заказа можно оплатить бонусами */
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
        $this->input = $inputCalculator;
        $this->output = $outputCalculator;
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
        return $this->input->basketItems->filter(function ($basketItem) use ($brandIds, $exceptOfferIds, $merchantId) {
            return $brandIds->contains($basketItem['brand_id'])
                && !$exceptOfferIds->contains($basketItem['id'])
                && (!$merchantId || $basketItem['merchant_id'] == $merchantId);
        })->pluck('offer_id');
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
        return $this->input->basketItems->filter(function ($basketItem) use (
            $categoryIds,
            $exceptBrandIds,
            $exceptOfferIds,
            $merchantId
        ) {
            $categories = InputCalculator::getAllCategories();
            $offerCategory = $categories->has($basketItem['category_id'])
                ? $categories[$basketItem['category_id']]
                : null;

            return $offerCategory
                && $categoryIds->reduce(function ($carry, $categoryId) use ($offerCategory, $categories) {
                    return $carry ||
                        (
                            $categories->has($categoryId)
                            && $categories[$categoryId]->isSelfOrAncestorOf($offerCategory)
                        );
                })
                && !$exceptBrandIds->contains($basketItem['brand_id'])
                && !$exceptOfferIds->contains($basketItem['id'])
                && (!$merchantId || $basketItem['merchant_id'] == $merchantId);
        })->pluck('offer_id');
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
        $options[Option::KEY_BONUS_PER_RUBLES] ??= self::DEFAULT_BONUS_PER_RUBLES;
        $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT] ??= self::DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT;
        $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_DISCOUNT_PRODUCT] ??= self::DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_DISCOUNT_PRODUCT;
        $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER] ??= self::DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_ORDER;
        $this->options = $options;
    }
}
