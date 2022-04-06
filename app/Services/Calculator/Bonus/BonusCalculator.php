<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Bonus\Bonus;
use App\Models\Option\Option;
use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;
use Illuminate\Support\Collection;

/**
 * Class BonusCalculator
 * @package App\Services\Calculator\Bonus
 */
class BonusCalculator extends AbstractCalculator
{
    /**
     * Список возможных бонусов
     * @var Collection
     */
    protected $bonuses;

    /** @var Collection */
    protected $appliedBonuses;

    /**
     * Количество бонусов для каждого оффера:
     * [offer_id => ['id' => bonus_id], ...]
     * @var Collection
     */
    protected $offersByBonuses;

    public function __construct(InputCalculator $inputCalculator, OutputCalculator $outputCalculator)
    {
        parent::__construct($inputCalculator, $outputCalculator);
    }

    public function calculate(bool $checkPermissions = true)
    {
        if ($checkPermissions && !$this->checkPermissions()) {
            return;
        }

        $this->bonuses = collect();
        $this->appliedBonuses = collect();
        $this->offersByBonuses = collect();

        $this->fetchActiveBonuses()->apply();

        $this->input->basketItems->transform(function ($basketItem) {
            $bonuses = $this->offersByBonuses[$basketItem['offer_id']] ?? collect();
            $basketItem['bonus'] = $bonuses->reduce(function ($carry, $bonus) use ($basketItem) {
                return $carry + $bonus['bonus'] * ($basketItem['qty'] ?? 1);
            }) ?? 0;

            $basketItem['bonuses'] = $bonuses;
            return $basketItem;
        });

        $this->output->appliedBonuses = $this->appliedBonuses;
    }

    /**
     * @return bool
     */
    protected function checkPermissions()
    {
        $availableRoles = $this->getOption(Option::KEY_ROLES_AVAILABLE_FOR_BONUSES) ?? [];
        $currentRoles = $this->input->customer['roles'] ?? [];
        return count(array_intersect($availableRoles, $currentRoles)) > 0;
    }

    /**
     * @return $this
     */
    protected function apply()
    {
        /** @var Bonus $bonus */
        foreach ($this->bonuses as $bonus) {
            $bonusValue = 0;
            switch ($bonus->type) {
                case Bonus::TYPE_OFFER:
                case Bonus::TYPE_ANY_OFFER:
                    # Бонусы на офферы
                    $offerIds = $bonus->type === Bonus::TYPE_OFFER
                        ? $bonus->offers->pluck('offer_id')
                        : $this->input->basketItems->pluck('offer_id')->unique();

                    $bonusValue = $this->applyBonusToOffer($bonus, $offerIds);
                    break;
                case Bonus::TYPE_BRAND:
                case Bonus::TYPE_ANY_BRAND:
                    # Бонусы на бренды
                    /** @var Collection $brandIds */
                    $brandIds = $bonus->type === Bonus::TYPE_BRAND
                        ? $bonus->brands->pluck('brand_id')
                        : $this->input->brands;
                    # За исключением офферов
                    $exceptOfferIds = $bonus->offers->pluck('offer_id');
                    # Отбираем нужные офферы
                    $offerIds = $this->filterForBrand($brandIds, $exceptOfferIds, null);
                    $bonusValue = $this->applyBonusToOffer($bonus, $offerIds);
                    break;
                case Bonus::TYPE_CATEGORY:
                case Bonus::TYPE_ANY_CATEGORY:
                    # Скидка на категории
                    /** @var Collection $categoryIds */
                    $categoryIds = $bonus->type === Bonus::TYPE_CATEGORY
                        ? $bonus->categories->pluck('category_id')
                        : $this->input->categories;
                    $categoryIds = $categoryIds->flip();
                    # За исключением брендов
                    $exceptBrandIds = $bonus->brands->pluck('brand_id');
                    # За исключением офферов
                    $exceptOfferIds = $bonus->offers->pluck('offer_id');
                    # Отбираем нужные офферы
                    $offerIds = $this->filterForCategory($categoryIds, $exceptBrandIds, $exceptOfferIds, null);
                    $bonusValue = $this->applyBonusToOffer($bonus, $offerIds);
                    break;
                case Bonus::TYPE_SERVICE:
                case Bonus::TYPE_ANY_SERVICE:
                    // todo
                    break;
                case Bonus::TYPE_CART_TOTAL:
                    $price = $this->input->getPriceOrders();
                    $bonusValue = $this->priceToBonusValue($price, $bonus);
                    break;
            }

            if ($bonusValue > 0) {
                $this->appliedBonuses->put($bonus->id, [
                    'id' => $bonus->id,
                    'name' => $bonus->name,
                    'type' => $bonus->type,
                    'value' => $bonus->value,
                    'value_type' => $bonus->value_type,
                    'valid_period' => $bonus->valid_period,
                    'promo_code_only' => $bonus->promo_code_only,
                    'bonus' => $bonusValue,
                ]);
            }
        }

        return $this;
    }

    /**
     * @param $offerIds
     *
     * @return bool|int
     */
    protected function applyBonusToOffer(Bonus $bonus, $offerIds)
    {
        $offerIds = $offerIds->filter(function ($offerId) {
            return (bool) $this->input->basketItems->where('offer_id', $offerId)->first();
        });

        if ($offerIds->isEmpty()) {
            return false;
        }

        $totalBonusValue = 0;
        foreach ($offerIds as $offerId) {
            $offer = $this->input->basketItems->where('offer_id', $offerId)->first();
            //$bonusValue = $this->priceToBonusValue($offer['price'], $bonus);
            $offerPriceWithDiscount = isset($offer['cost'])
                ? $offer['discounts']
                    ? $offer['cost'] - array_sum(array_column($offer['discounts'], 'change'))
                    : $offer['cost']
                : $offer['price'];
            $bonusValue = $this->priceToBonusValue($offerPriceWithDiscount, $bonus);

            if (!$this->offersByBonuses->has($offerId)) {
                $this->offersByBonuses->put($offerId, collect());
            }

            $this->offersByBonuses[$offerId]->push([
                'id' => $bonus->id,
                'bonus' => $bonusValue,
                'value' => $bonus->value,
                'value_type' => $bonus->value_type,
            ]);
            $totalBonusValue += $bonusValue * $offer['qty'];
        }

        return $totalBonusValue;
    }

    /**
     * @param $price
     *
     * @return int
     */
    protected function priceToBonusValue($price, Bonus $bonus)
    {
        switch ($bonus->value_type) {
            case Bonus::VALUE_TYPE_PERCENT:
                return round($price * $bonus->value / 100);
            case Bonus::VALUE_TYPE_ABSOLUTE:
                return $bonus->value;
        }

        return 0;
    }

    /**
     * @return $this
     */
    protected function fetchActiveBonuses()
    {
        $this->bonuses = Bonus::query()
            ->where(function ($query) {
                $query->where('promo_code_only', false);
                $promoCodeBonusId = $this->input->promoCodeBonus->id ?? null;
                if ($promoCodeBonusId) {
                    $query->orWhere('id', $promoCodeBonusId);
                }
            })
            ->active()
            ->get();

        return $this;
    }
}
