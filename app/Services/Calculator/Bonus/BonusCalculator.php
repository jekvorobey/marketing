<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Bonus\Bonus;
use App\Models\Option\Option;
use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\Discount\Filters\OfferCategoryFilter;
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
     * [basket_item_id => ['id' => bonus_id], ...]
     * @var Collection
     */
    protected $basketItemsByBonuses;

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
        $this->basketItemsByBonuses = collect();

        $this->fetchActiveBonuses()->apply();

        $this->input->basketItems->transform(function ($basketItem) {
            $bonuses = $this->basketItemsByBonuses[$basketItem['id']] ?? collect();
            $basketItem['bonus'] = $bonuses->reduce(function ($carry, $bonus) use ($basketItem) {
                return $carry + $bonus['bonus'] * ($basketItem['qty'] ?? 1);
            }) ?? 0;
            $basketItem['bonuses'] = $bonuses;

            return $basketItem;
        });

        $this->output->appliedBonuses = $this->appliedBonuses;
    }

    protected function checkPermissions(): bool
    {
        $availableRoles = $this->getOption(Option::KEY_ROLES_AVAILABLE_FOR_BONUSES) ?? [];
        $currentRoles = $this->input->customer['roles'] ?? [];

        return count(array_intersect($availableRoles, $currentRoles)) > 0;
    }

    protected function apply(): self
    {
        /** @var Bonus $bonus */
        foreach ($this->bonuses as $bonus) {
            $bonusValue = 0;
            switch ($bonus->type) {
                case Bonus::TYPE_OFFER:
                case Bonus::TYPE_ANY_OFFER:
                    # Бонусы на офферы
                    $basketItemsIds = $bonus->type === Bonus::TYPE_OFFER
                        ? $this->input->basketItems->whereIn('offer_id', $bonus->offers->pluck('offer_id')->toArray())->pluck('id')
                        : $this->input->basketItems->pluck('id');

                    $bonusValue = $this->applyBonusToBasketItem($bonus, $basketItemsIds);
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
                    $basketItemsIds = $this->input->basketItems->whereIn('offer_id', $offerIds->toArray())->pluck('id');
                    $bonusValue = $this->applyBonusToBasketItem($bonus, $basketItemsIds);
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
                    $filter = new OfferCategoryFilter();
                    $filter
                        ->setCategoryIds($categoryIds)
                        ->setExceptBrandIds($exceptBrandIds)
                        ->setExceptOfferIds($exceptOfferIds)
                        ->setBasketItems($this->input->basketItems);
                    $offerIds = $filter->getFilteredOfferIds();
                    $basketItemsIds = $this->input->basketItems->whereIn('offer_id', $offerIds->toArray())->pluck('id');
                    $bonusValue = $this->applyBonusToBasketItem($bonus, $basketItemsIds);
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

    protected function applyBonusToBasketItem(Bonus $bonus, Collection $basketItemsIds): float|bool|int
    {
        if ($basketItemsIds->isEmpty()) {
            return false;
        }

        $totalBonusValue = 0;
        foreach ($basketItemsIds as $basketItemId) {
            $basketItem = $this->input->basketItems->get($basketItemId);
            //$bonusValue = $this->priceToBonusValue($offer['price'], $bonus);
            $basketItemPriceWithDiscount = $basketItem['price'];
            $bonusValue = $this->priceToBonusValue($basketItemPriceWithDiscount, $bonus);

            if (!$this->basketItemsByBonuses->has($basketItemId)) {
                $this->basketItemsByBonuses->put($basketItemId, collect());
            }

            $this->basketItemsByBonuses[$basketItemId]->push([
                'id' => $bonus->id,
                'bonus' => $bonusValue,
                'value' => $bonus->value,
                'value_type' => $bonus->value_type,
            ]);
            $totalBonusValue += $bonusValue * $basketItem['qty'];
        }

        return $totalBonusValue;
    }

    protected function priceToBonusValue($price, Bonus $bonus): int
    {
        switch ($bonus->value_type) {
            case Bonus::VALUE_TYPE_PERCENT:
                return round($price * $bonus->value / 100);
            case Bonus::VALUE_TYPE_ABSOLUTE:
                return $bonus->value;
        }

        return 0;
    }

    protected function fetchActiveBonuses(): self
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
