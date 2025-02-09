<?php

namespace App\Services\Calculator\Discount;

use App\Models\Discount\Discount;
use App\Services\Calculator\InputCalculator;
use Illuminate\Support\Collection;

class DiscountOutput
{
    private InputCalculator $input;
    private Collection $discounts;
    private Collection $appliedDiscounts;
    private Collection $basketItemsByDiscounts;

    public function __construct(
        InputCalculator $input,
        Collection $discounts,
        Collection $basketItemsByDiscounts,
        Collection $appliedDiscounts
    ) {
        $this->input = $input;
        $this->discounts = $discounts;
        $this->appliedDiscounts = $appliedDiscounts;
        $this->basketItemsByDiscounts = $basketItemsByDiscounts;
    }

    public function getModifiedAppliedDiscounts(): Collection
    {
        return $this->appliedDiscounts;
    }

    public function getModifiedBasketItemsByDiscounts(): Collection
    {
        return $this->basketItemsByDiscounts;
    }

    public function getBasketItems(): Collection
    {
        return $this->input->basketItems->transform(function ($basketItem, $basketItemId) {
            if (!$this->basketItemsByDiscounts->has($basketItemId)) {
                $basketItem['discount'] = null;
                $basketItem['discounts'] = null;
                return $basketItem;
            }

            $basketItem['price'] = $this->roundedBasketItemPrice($basketItem['price']);

            $this->correctBasketItemDiscountsAfterRound($basketItem, $basketItemId);

            return $basketItem;
        });
    }

    /** Округление конечной цены */
    private function roundedBasketItemPrice($price): int
    {
        return $price > 1 ? floor($price) : ceil($price);
    }

    /** Корректируем суммы примененных скидок на величину, отброшенную после округления цены товара */
    private function correctBasketItemDiscountsAfterRound(&$basketItem, $basketItemId): void
    {
        $roundOff = $this->calcRoundOffForBasketItem($basketItem, $basketItemId);

        if (!$roundOff['basicError'] && !$roundOff['correction'] && !$roundOff['diffPerDiscount']) {
            return;
        }

        $discounts = $this->basketItemsByDiscounts[$basketItemId];

        $discounts->transform(function ($discount) use (&$roundOff) {
            $resultedDiff = $roundOff['diffPerDiscount'] + $roundOff['correction'];
            $roundOff['correction'] = 0;

            $discount['change'] = round($discount['change'] + $resultedDiff, 2);

            $appliedDiscount = $this->appliedDiscounts->get($discount['id']);
            $appliedDiscount['change'] = round($appliedDiscount['change'] + $resultedDiff * $roundOff['affectedQty'], 2);
            $this->appliedDiscounts->put($discount['id'], $appliedDiscount);

            return $discount;
        });

        //скидка basket_item после округлений
        $finalDiscount = round($basketItem['cost'] - $basketItem['price'], 2);

        //сумма всех скорректированных скидок basket_item
        $correctedDiscountsSum = round($this->basketItemsByDiscounts[$basketItemId]->sum('change'), 2);

        //если сумма скорректированных скидок отличается от итоговой округленной скидки,
        //то добавляем разницу в одну из скидок
        $finalDiff = round($finalDiscount - $correctedDiscountsSum, 2);

        if ($finalDiff !== 0) {
            $discountForCorrection = $discounts->last();
            $appliedDiscount = $this->appliedDiscounts->get($discountForCorrection['id']);
            $appliedDiscount['change'] = round($appliedDiscount['change'] + $finalDiff, 2);
            $this->appliedDiscounts->put($discountForCorrection['id'], $appliedDiscount);
        }

        $basketItem['discount'] = $finalDiscount;
        $basketItem['discounts'] = $this->basketItemsByDiscounts[$basketItemId]->toArray();
    }

    /** Данные по погрешности округления цены товара */
    private function calcRoundOffForBasketItem($basketItem, $basketItemId): ?array
    {
        if (!isset($basketItem['discount'])) {
            return [
                'basicError' => 0,
                'diffPerDiscount' => 0,
                'correction' => 0,
                'affectedQty' => 0,
            ];
        }

        # Финальная скидка, в которую входит сама скидка и ошибка округления
        $finalDiscount = $basketItem['cost'] - $basketItem['price'];
        $basicError = round($finalDiscount - $basketItem['discount'], 4);

        $basicDiscountsNumber = $this->basketItemsByDiscounts[$basketItemId]->filter(function ($discount) {
            return true; //!$this->input->bundles->contains($discount['id']); //@todo почему то исключались бандлы из округления - убрал фильтрацию
        })->count();

        $diffPerDiscount = $basicDiscountsNumber > 0
            ? round($basicError / $basicDiscountsNumber, 2)
            : 0;

        $correctionValue = $diffPerDiscount
            ? round($basicError - $diffPerDiscount * $basicDiscountsNumber, 3)
            : 0;

        return [
            'basicError' => $basicError,
            'diffPerDiscount' => $diffPerDiscount,
            'correction' => $correctionValue,
            'affectedQty' => $basketItem['qty'],
        ];
    }

    public function getOutputFormat(): Collection
    {
        $discounts = $this->discounts->filter(function ($discount) {
            return $this->appliedDiscounts->has($discount->id) && !empty($this->appliedDiscounts[$discount->id]['change']);
        })->keyBy('id');

        $items = collect();
        foreach ($discounts as $discount) {
            $discountId = $discount->id;
            $conditions = $discount->conditions
                ? $discount->conditions->toArray()
                : [];
            $extType = Discount::getExternalType($discount['type'], $conditions, $discount->promo_code_only);
            $isPromoCodeDiscount = $this->input
                ->promoCodeDiscounts
                ->pluck('id')
                ->contains($discountId);

            $items->push([
                'id' => $discountId,
                'name' => $discount->name,
                'type' => $discount->type,
                'external_type' => $extType,
                'change' => $this->appliedDiscounts[$discountId]['change'],
                'merchant_id' => $discount->merchant_id,
                'visible_in_catalog' => $extType === Discount::EXT_TYPE_OFFER,
                'promo_code_only' => $discount->promo_code_only,
                'max_priority' => $discount->max_priority,
                'summarizable_with_all' => $discount->summarizable_with_all,
                'promo_code' => $isPromoCodeDiscount ? $this->input->promoCode : null,
            ]);
        }

        return $items;
    }
}
