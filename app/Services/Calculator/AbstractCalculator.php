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
     * @return OutputCalculator
     */
    public function getOutput(): OutputCalculator
    {
        return $this->output;
    }

    /**
     * @return InputCalculator
     */
    public function getInput(): InputCalculator
    {
        return $this->input;
    }

    /**
     * @param $brandIds
     * @param $exceptOfferIds
     * @param $merchantId
     * @return Collection
     */
    protected function filterForBrand($brandIds, $exceptOfferIds, $merchantId): Collection
    {
        return $this->input->basketItems->filter(function ($basketItem) use ($brandIds, $exceptOfferIds, $merchantId) {
            return $brandIds->contains($basketItem['brand_id'])
                && !$exceptOfferIds->contains($basketItem['offer_id'])
                && (!$merchantId || $basketItem['merchant_id'] == $merchantId);
        })->pluck('offer_id');
    }

    /**
     * Получить опцию по ключу (с кэшем в рамках процесса)
     * @param mixed $key
     */
    protected function getOption($key): mixed
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
