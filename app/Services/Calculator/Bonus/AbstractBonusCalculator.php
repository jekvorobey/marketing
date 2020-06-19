<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Bonus\ProductBonusOption\ProductBonusOption;
use App\Models\Option\Option;
use App\Services\Calculator\AbstractCalculator;

abstract class AbstractBonusCalculator extends AbstractCalculator
{
    private $productBonusOptions;
    
    /**
     * @param $bonus
     *
     * @return int
     */
    protected function bonusToPrice($bonus)
    {
        $k = $this->getOption(Option::KEY_BONUS_PER_RUBLES);
        return AbstractCalculator::round($bonus * $k, AbstractCalculator::FLOOR);
    }
    
    /**
     * @param $price
     *
     * @return int
     */
    protected function priceToBonus($price)
    {
        $k = $this->getOption(Option::KEY_BONUS_PER_RUBLES);
        if (!$k) {
            return 0;
        }
        return AbstractCalculator::round($price / $k, AbstractCalculator::FLOOR);
    }
    
    /**
     * Получить опцию бонусов для указанного товара (с кэшем в рамках процесса)
     * @param int $productId
     * @param mixed $key
     * @return mixed
     */
    protected function getBonusProductOption($productId, $key)
    {
        $this->loadProductBonusOptions();
        return $this->productBonusOptions[$productId][$key] ?? null;
    }
    
    /**
     * Получить размер стоимости оффера, который можно погасить бонусом.
     * @param array $offer
     *
     * @return int
     */
    protected function maxBonusPriceForOffer($offer)
    {
        $productId = $offer['product_id'];
        $percent = $this->getBonusProductOption($productId, ProductBonusOption::MAX_PERCENTAGE_PAYMENT);
        if ($percent === null) {
            $percent = $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT);
        }
        return self::percent($offer['price'], $percent);
    }
    
    /**
     * Загрузить опции бонусов всех товаров для текущего input (не грузит повторно)
     */
    private function loadProductBonusOptions()
    {
        if ($this->productBonusOptions) {
            return;
        }
        $offerProductIds = $this->input->offers->pluck('product_id', 'id');
        $this->productBonusOptions = ProductBonusOption::query()
            ->whereIn('product_id', $offerProductIds->values())
            ->get()
            ->pluck('value', 'product_id');
    }
}