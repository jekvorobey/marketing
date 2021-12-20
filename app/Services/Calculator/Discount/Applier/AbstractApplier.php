<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\InputCalculator;
use Illuminate\Support\Collection;

abstract class AbstractApplier
{
    /**
     * Если по какой-то скидке есть ограничение на максимальный итоговый размер скидки по офферу
     * @var array [discount_id => ['value' => value, 'value_type' => value_type], ...]
     */
    protected array $maxValueByDiscount = [];
    protected InputCalculator $input;
    protected Collection $offersByDiscounts;
    protected Collection $appliedDiscounts;

    public function __construct(InputCalculator $input, Collection $offersByDiscounts, Collection $appliedDiscounts)
    {
        $this->input = $input;
        $this->offersByDiscounts = $offersByDiscounts;
        $this->appliedDiscounts = $appliedDiscounts;
    }

    abstract public function apply(Discount $discount): ?float;

    public function getModifiedOffersByDiscounts(): Collection
    {
        return $this->offersByDiscounts;
    }

    public function getModifiedInputOffers(): Collection
    {
        return $this->input->offers;
    }

    /**
     * Можно ли применить скидку к офферу
     */
    protected function applicableToOffer(Discount $discount, $offerId): bool
    {
        if (
            $discount->type === Discount::DISCOUNT_TYPE_BUNDLE_OFFER ||
            $discount->type === Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS ||
            $discount->type === Discount::DISCOUNT_TYPE_ANY_BUNDLE
        ) {
            return true;
        }

        if ($this->appliedDiscounts->isEmpty() || !$this->offersByDiscounts->has($offerId)) {
            return true;
        }

        /** @var Collection $discountIdsForOffer */
        $discountIdsForOffer = $this->offersByDiscounts[$offerId]->pluck('id');

        $discountConditions = $discount->conditions->where('type', DiscountCondition::DISCOUNT_SYNERGY);
        /** @var DiscountCondition $condition */
        foreach ($discountConditions as $condition) {
            $synergyDiscountIds = $condition->getSynergy();
            if ($discountIdsForOffer->intersect($synergyDiscountIds)->count() !== $discountIdsForOffer->count()) {
                return false;
            }

            if ($condition->getMaxValueType()) {
                $this->maxValueByDiscount[$discount->id] = [
                    'value_type' => $condition->getMaxValueType(),
                    'value' => $condition->getMaxValue(),
                ];
            }

            return true;
        }

        return false;
    }

    protected function addOfferByDiscount(int $offerId, Discount $discount, float $change): void
    {
        if (!$this->offersByDiscounts->has($offerId)) {
            $this->offersByDiscounts->put($offerId, collect());
        }

        $this->offersByDiscounts[$offerId]->push([
            'id' => $discount->id,
            'change' => $change,
            'value' => $discount->value,
            'value_type' => $discount->value_type,
        ]);
    }
}
