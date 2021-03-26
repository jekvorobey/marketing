<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Discount\Discount;

class BonusMayBeSpentCalculator extends AbstractBonusCalculator
{
    public function calculate()
    {
        if (!$this->bonusSettingsIsSet()) {
            return;
        }
        $totalBonusPrice = 0;
        $this->setBonusToEachOffer(null, function ($affectedItem, $changePriceValue) use (&$totalBonusPrice) {
            @([
                'offer_id' => $offerId,
                'bundle_id'   => $bundleId
            ] = $affectedItem);

            if ($bundleId) {
                $item = &$this->input->offers[$offerId]['bundles'][$bundleId];
            } else {
                $item = &$this->input->offers[$offerId];
            }

            $discount = $this->changePrice(
                $item,
                $changePriceValue,
                Discount::DISCOUNT_VALUE_TYPE_RUB,
                false,
                self::LOWEST_POSSIBLE_PRICE
            );
            $totalBonusPrice += $discount * $affectedItem['qty'];
            return $changePriceValue;
        });

        /*
         * По сути дела - костыль
         * В МС не приходят данные о максимально возможном количестве бонусов, которое можно списать на заказ
         * поэтому дополнительно считаем здесь
         */
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
        $totalBonusPrice = 0;
        foreach ($items as $item) {
            $maxSpendForOffer = (!$item['has_discount'])
                ? $this->maxBonusPriceForOffer($item)
                : $this->maxBonusPriceForDiscountOffer($item);
            $totalBonusPrice += $maxSpendForOffer;
        }
        /*
         * Конец костыля
         */

        $this->output->maxSpendableBonus = $this->priceToBonus(min($totalBonusPrice, $this->input->customerBonusAmount));
    }
}
