<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Bonus\ProductBonusOption\ProductBonusOption;
use App\Models\Option\Option;
use App\Services\Calculator\AbstractCalculator;
use Illuminate\Support\Collection;

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

    protected function needCalculateBonus(): bool
    {
        return $this->bonusSettingsIsSet() && $this->input->bonus > 0;
    }

    protected function bonusSettingsIsSet()
    {
        return $this->getOption(Option::KEY_BONUS_PER_RUBLES) > 0
        && $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT) > 0
        && $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER) > 0;
    }

    /**
     * @return Collection
     */
    protected function sortOffers()
    {
        return $this->input->offers->sortBy(function ($offer) {
            return $this->maxBonusPriceForOffer($offer);
        })->keys();
    }

    protected function setBonusToEachOffer($bonusPrice, $callback)
    {
        $orderPrice = $this->input->getPriceOrders();
        $maxSpendForOrder = AbstractCalculator::percent($orderPrice, $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER));
        $spendForOrder = $bonusPrice === null ? $maxSpendForOrder : min($bonusPrice, $maxSpendForOrder);

        $offerIds = $this->sortOffers();

        foreach ($offerIds as $offerId) {
            $offer = $this->input->offers[$offerId];
            $maxSpendForOffer = $this->maxBonusPriceForOffer($offer);
            $offerPrice       = $offer['price'];
            $percent          = $offer['price'] > 0 ? $offerPrice / $orderPrice * 100 : 0;
            $spendForOffer    = AbstractCalculator::percent($spendForOrder, $percent, AbstractCalculator::ROUND);
            $changePriceValue = min($maxSpendForOffer, $spendForOffer);
            if ($spendForOrder < $changePriceValue * $offer['qty']) {
                $spendForOffer    = AbstractCalculator::percent($spendForOrder, $percent, AbstractCalculator::FLOOR);
                $changePriceValue = min($maxSpendForOffer, $spendForOffer);
            }

            $discount = $callback($offer, $changePriceValue);

            $spendForOrder -= $discount * $offer['qty'];
            $orderPrice    -= $offerPrice * $offer['qty'];
        }
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
