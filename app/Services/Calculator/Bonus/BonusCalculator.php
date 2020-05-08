<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Bonus\Bonus;
use Illuminate\Support\Collection;
use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;

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

    /**
     * @var Collection
     */
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

    public function calculate()
    {
        $this->bonuses         = collect();
        $this->appliedBonuses  = collect();
        $this->offersByBonuses = collect();

        $this->fetchActiveBonuses()->apply();

        $this->input->offers->transform(function ($offer, $offerId) {
            $bonuses = $this->offersByBonuses[$offerId] ?? collect();
            $offer['bonus'] = $bonuses->reduce(function ($carry, $bonus) use ($offer) {
                return $carry + $bonus['bonus'] * ($offer['qty'] ?? 1);
            }) ?? 0;

            $offer['bonuses']   = $bonuses;
            return $offer;
        });

        $this->output->appliedBonuses = $this->appliedBonuses;
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
                    $offerIds = ($bonus->type === Bonus::TYPE_OFFER)
                        ? $bonus->offers->pluck('offer_id')
                        : $this->input->offers->pluck('id');

                    $bonusValue = $this->applyBonusToOffer($bonus, $offerIds);
                    break;
                case Bonus::TYPE_BRAND:
                case Bonus::TYPE_ANY_BRAND:
                    # Бонусы на бренды
                    /** @var Collection $brandIds */
                    $brandIds = ($bonus->type === Bonus::TYPE_BRAND)
                        ? $bonus->brands->pluck('brand_id')
                        : $this->input->brands;
                    # За исключением офферов
                    $exceptOfferIds = $bonus->offers->pluck('offer_id');
                    # Отбираем нужные офферы
                    $offerIds   = $this->filterForBrand($brandIds, $exceptOfferIds, null);
                    $bonusValue = $this->applyBonusToOffer($bonus, $offerIds);
                    break;
                case Bonus::TYPE_CATEGORY:
                case Bonus::TYPE_ANY_CATEGORY:
                    # Скидка на категории
                    /** @var Collection $categoryIds */
                    $categoryIds = ($bonus->type === Bonus::TYPE_BRAND)
                        ? $bonus->categories->pluck('category_id')
                        : $this->input->categories;
                    # За исключением брендов
                    $exceptBrandIds = $bonus->brands->pluck('brand_id');
                    # За исключением офферов
                    $exceptOfferIds = $bonus->offers->pluck('offer_id');
                    # Отбираем нужные офферы
                    $offerIds   = $this->filterForCategory($categoryIds, $exceptBrandIds, $exceptOfferIds, null);
                    $bonusValue = $this->applyBonusToOffer($bonus, $offerIds);
                    break;
                case Bonus::TYPE_SERVICE:
                case Bonus::TYPE_ANY_SERVICE:
                    // todo
                    break;
                case Bonus::TYPE_CART_TOTAL:
                    $price      = $this->input->getPriceOrders();
                    $bonusValue = $this->priceToBonusValue($price, $bonus);
                    break;
            }

            if ($bonusValue > 0) {
                $this->appliedBonuses->put($bonus->id, [
                    'id'              => $bonus->id,
                    'name'            => $bonus->name,
                    'type'            => $bonus->type,
                    'value'           => $bonus->value,
                    'value_type'      => $bonus->value_type,
                    'valid_period'    => $bonus->valid_period,
                    'promo_code_only' => $bonus->promo_code_only,
                    'bonus'           => $bonusValue,
                ]);
            }
        }

        return $this;
    }

    /**
     * @param Bonus $bonus
     * @param       $offerIds
     *
     * @return bool|int
     */
    protected function applyBonusToOffer(Bonus $bonus, $offerIds)
    {
        $offerIds = $offerIds->filter(function ($offerId) use ($bonus) {
            return $this->input->offers->has($offerId);
        });

        if ($offerIds->isEmpty()) {
            return false;
        }

        $totalBonusValue = 0;
        foreach ($offerIds as $offerId) {
            $offer      = &$this->input->offers[$offerId];
            $bonusValue = $this->priceToBonusValue($offer['price'], $bonus);

            if (!$this->offersByBonuses->has($offerId)) {
                $this->offersByBonuses->put($offerId, collect());
            }

            $this->offersByBonuses[$offerId]->push([
                'id'         => $bonus->id,
                'bonus'      => $bonusValue,
                'value'      => $bonus->value,
                'value_type' => $bonus->value_type
            ]);
            $totalBonusValue += $bonusValue * $offer['qty'];
        }

        return $totalBonusValue;
    }

    /**
     * @param       $price
     * @param Bonus $bonus
     *
     * @return int
     */
    protected function priceToBonusValue($price, Bonus $bonus)
    {
        switch ($bonus->value_type) {
            case Bonus::VALUE_TYPE_PERCENT:
                return round($price * $bonus->value / 100);
            case Bonus::VALUE_TYPE_RUB:
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
