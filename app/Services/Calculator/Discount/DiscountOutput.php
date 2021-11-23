<?php

namespace App\Services\Calculator\Discount;

use App\Models\Discount\Discount;
use App\Services\Calculator\InputCalculator;
use Illuminate\Support\Collection;

class DiscountOutput
{
    private InputCalculator $input;
    private Collection $discounts;
    private Collection $relations;
    private Collection $appliedDiscounts;
    private Collection $offersByDiscounts;

    public function __construct(
        InputCalculator $input,
        Collection $discounts,
        Collection $relations,
        Collection $offersByDiscounts,
        Collection $appliedDiscounts
    ) {
        $this->input = $input;
        $this->discounts = $discounts;
        $this->relations = $relations;
        $this->appliedDiscounts = $appliedDiscounts;
        $this->offersByDiscounts = $offersByDiscounts;
    }

    public function getOffers(): Collection
    {
        return $this->input->offers->transform(function ($offer, $offerId) {

            if (!$this->offersByDiscounts->has($offerId)) {
                $offer['discount'] = null;
                $offer['discounts'] = null;
                return $offer;
            }
            # Конечная цена товара после применения скидки всегда округляется до целого
            $offer['price'] = round($offer['price']);

            $roundOffs = collect();
            # Погрешность после применения базовых товарных скидок (не бандлов)
            $basicError = 0;
            if (isset($offer['discount'])) {
                # Финальная скидка, в которую входит сама скидка и ошибка округления
                $finalDiscount = $offer['cost'] - $offer['price'];
                $basicError = $finalDiscount - $offer['discount'];
                $basicDiscountsNumber = $this->offersByDiscounts[$offerId]->filter(function ($discount) {
                    return !$this->input->bundles->contains($discount['id']);
                })->count();
                $diffPerDiscount = $basicDiscountsNumber > 0
                    ? round($basicError / $basicDiscountsNumber, 2)
                    : 0;
                $correctionValue = $diffPerDiscount
                    ? round($basicError - $diffPerDiscount * $basicDiscountsNumber, 3)
                    : 0;

                $roundOffs->put(0, [
                    'error' => $diffPerDiscount,
                    'correction' => $correctionValue,
                    'affectedQty' => $offer['qty'],
                ]);
            }

            $offer['bundles']->each(function ($bundle, $id) use ($roundOffs, $offer, &$basicError) {
                if ($id == 0 || !isset($bundle['discount'])) {
                    return;
                }
                $finalDiscount = $offer['cost'] - $bundle['price'];
                $roundOffError = $finalDiscount - $bundle['discount'];

                $roundOffs->put($id, [
                    'error' => round($roundOffError - $basicError, 2),
                    'correction' => 0,
                    'affectedQty' => $bundle['qty'],
                ]);
            });

            $this->offersByDiscounts[$offerId]->transform(function ($discount) use ($roundOffs) {
                $key = $roundOffs->has($discount['id']) ? $discount['id'] : 0;
                $roundOff = $roundOffs->get($key);
                if (!$roundOff) {
                    return $discount;
                }

                $resultedDiff = $roundOff['error'] + $roundOff['correction'];
                $roundOff['correction'] = 0;
                $roundOffs->put($key, $roundOff);

                $discount['change'] = round($discount['change'] + $resultedDiff, 2);

                $appliedDiscount = $this->appliedDiscounts->get($discount['id']);
                $appliedDiscount['change'] = round($appliedDiscount['change'] + $resultedDiff * $roundOff['affectedQty'], 2);
                $this->appliedDiscounts->put($discount['id'], $appliedDiscount);

                return $discount;
            });

            /** @var Collection|null $discountsWithoutBundles */
            $discountsWithoutBundles = $this->offersByDiscounts[$offerId]->filter(function ($discount) {
                return !$this->input->bundles->contains($discount['id']);
            })->values();

            $sum = round($discountsWithoutBundles->sum('change'), 2);
            $offer['discount'] = $sum;

            $offer['discounts'] = $discountsWithoutBundles->toArray();

            /** @var Collection|null $discountsWithBundles */
            $discountsWithBundles = $this->offersByDiscounts[$offerId]->filter(function ($discount) {
                return $this->input->bundles->contains($discount['id']);
            })->keyBy('id');

            if ($discountsWithBundles && !$discountsWithBundles->isEmpty()) {
                $offer['bundles']->transform(function ($bundle, $bundleId) use ($sum, $discountsWithBundles, $discountsWithoutBundles) {
                    if ($bundleId && $discountsWithBundles->has($bundleId)) {
                        $bundle['discount'] = $sum + $discountsWithBundles[$bundleId]['change'];
                        $bundle['discounts'] = $discountsWithoutBundles->push($discountsWithBundles[$bundleId]);
                    }
                    return $bundle;
                });
            }

            return $offer;
        });
    }

    public function getOutputFormat(): Collection
    {
        $discounts = $this->discounts->filter(function ($discount) {
            return $this->appliedDiscounts->has($discount->id);
        })->keyBy('id');

        $items = collect();
        foreach ($discounts as $discount) {
            $discountId = $discount->id;
            $conditions = $this->relations['conditions']->has($discountId)
                ? $this->relations['conditions'][$discountId]->toArray()
                : [];

            $extType = Discount::getExternalType($discount['type'], $conditions, $discount->promo_code_only);
            $isPromoCodeDiscount = $this->input->promoCodeDiscount && $this->input->promoCodeDiscount->id = $discountId;
            $items->push([
                'id' => $discountId,
                'name' => $discount->name,
                'type' => $discount->type,
                'external_type' => $extType,
                'change' => $this->appliedDiscounts[$discountId]['change'],
                'merchant_id' => $discount->merchant_id,
                'visible_in_catalog' => $extType === Discount::EXT_TYPE_OFFER,
                'promo_code_only' => $discount->promo_code_only,
                'promo_code' => $isPromoCodeDiscount ? $this->input->promoCode : null,
            ]);
        }

        return $items;
    }
}
