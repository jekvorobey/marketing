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
    protected function maxBonusPriceForOffer(array $offer)
    {
        $productId = $offer['product_id'];
        $percent = $this->getBonusProductOption($productId, ProductBonusOption::MAX_PERCENTAGE_PAYMENT);
        if ($percent === null) {
            $percent = $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT);
        }
        return self::percent($offer['price'], $percent);
    }

    protected function maxBonusPriceForDiscountOffer(array $offer)
    {
        $productId = $offer['product_id'];
        $percent = $this->getBonusProductOption($productId, ProductBonusOption::MAX_PERCENTAGE_DISCOUNT_PAYMENT);
        if ($percent === null) {
            $percent = $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_DISCOUNT_PRODUCT);
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
     * @param Collection $items
     * @return Collection
     */
    protected function sortItems(Collection $items)
    {
        return $items->sortBy(function ($item) {
            return $this->maxBonusPriceForOffer($item);
        });
    }

    protected function setBonusToEachOffer($bonusPrice, $callback) {
        $items = $this->prepareItems();
        $sortedItems = $this->sortItems($items);

        $bonusRemains = $bonusPrice ?? $this->input->bonus;
        $lastBonusRemains = $bonusRemains;

        foreach ($sortedItems as $item) {
            $maxSpendForOffer = (!$item['has_discount'])
                ? $this->maxBonusPriceForOffer($item)
                : $this->maxBonusPriceForDiscountOffer($item);
            $bonusRemains -= $maxSpendForOffer;
            if ($bonusRemains < 0 && $sortedItems->count() == 1) {
                $callback($item, $this->input->bonus);
                continue;
            }
            if ($bonusRemains > 0) {
                $callback($item, $maxSpendForOffer);
                $lastBonusRemains = $bonusRemains;
            } else {
                $callback($item, $lastBonusRemains);
                $lastBonusRemains = 0;
            }
        }
    }

/*
    protected function setBonusToEachOffer($bonusPrice, $callback)
    {
        $items = $this->prepareItems();
        $orderPrice = $items->map(function ($item) {
            return $item['price'] * $item['qty'];
        })->sum();
        //$orderPrice = $this->input->getPriceOrders();
        $maxSpendForOrder = AbstractCalculator::percent($orderPrice, $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER));
        $spendForOrder = $bonusPrice === null ? $maxSpendForOrder : min($bonusPrice, $maxSpendForOrder);

        $sortedItems = $this->sortItems($items);

        foreach ($sortedItems as $item) {
            $maxSpendForOffer = (!$item['has_discount'])
                ? $this->maxBonusPriceForOffer($item)
                : $this->maxBonusPriceForDiscountOffer($item);
            $offerPrice = $item['price'];
            $percent = $item['price'] > 0 ? $offerPrice / $orderPrice * 100 : 0;
            **
             * Временное решение, пока не будут реализованы правила списания
             * $spendForOffer = AbstractCalculator::percent($maxSpendForOrder, $percent, AbstractCalculator::ROUND);
             *
            $spendForOffer = AbstractCalculator::percent($maxSpendForOffer, $percent, AbstractCalculator::ROUND);
            $changePriceValue = min($maxSpendForOffer, $spendForOffer);
            if ($spendForOrder < $changePriceValue * $item['qty']) {
                $spendForOffer = AbstractCalculator::percent($spendForOrder, $percent, AbstractCalculator::FLOOR);
                $changePriceValue = min($maxSpendForOffer, $spendForOffer);
            }

            $discount = $callback($item, $changePriceValue);

            $spendForOrder -= $discount * $item['qty'];
            $orderPrice -= $offerPrice * $item['qty'];
        }
    }
*/
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

    /**
     * @return Collection
     */
    private function prepareItems(): Collection
    {
        $items = collect();
        $this->input->offers->each(function ($offer) use ($items) {
            foreach ($offer['bundles'] as $id => $bundle) {
                $items->push([
                    'offer_id' => $offer['id'],
                    'product_id' => $offer['product_id'],
                    'qty' => $bundle['qty'],
                    'price' => $id == 0 ? $offer['price'] : $bundle['price'],
                    'bundle_id' => $this->input->bundles->contains($id) ? $id : null,
                    'has_discount' => (isset($offer['discount']) && $offer['discount'] > 0),
                ]);
            }
        });

        return $items;
    }
}
