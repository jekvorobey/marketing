<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Bonus\ProductBonusOption\ProductBonusOption;
use App\Models\Discount\Discount;
use App\Models\Option\Option;
use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;
use Illuminate\Support\Collection;

/**
 * Class BonusSpentCalculator
 * @package App\Services\Calculator\Bonus
 */
class BonusSpentCalculator extends AbstractCalculator
{
    const FLOOR = 1;
    const CEIL = 2;
    const ROUND = 3;

    const DEFAULT_BONUS_PER_RUBLES = 1;
    const DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT = 100;
    const DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_ORDER = 100;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    protected $productBonusOptions = [];

    /**
     * BonusSpentCalculator constructor.
     *
     * @param InputCalculator  $inputCalculator
     * @param OutputCalculator $outputCalculator
     */
    public function __construct(InputCalculator $inputCalculator, OutputCalculator $outputCalculator)
    {
        parent::__construct($inputCalculator, $outputCalculator);

        $options = Option::query()
            ->whereIn('key', [
                Option::KEY_BONUS_PER_RUBLES,
                Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT,
                Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER,
            ])
            ->get()
            ->pluck('value', 'key');

        $this->options = [
            Option::KEY_BONUS_PER_RUBLES => $options[Option::KEY_BONUS_PER_RUBLES]['value'] ?? self::DEFAULT_BONUS_PER_RUBLES,
            Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT => $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT]['value'] ?? self::DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT,
            Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER => $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER]['value'] ?? self::DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_ORDER,
        ];


        $offerProductIds = $this->input->offers->pluck('product_id', 'id');
        $this->productBonusOptions = ProductBonusOption::query()
            ->whereIn('product_id', $offerProductIds->values())
            ->get()
            ->pluck('value', 'product_id');
    }

    /**
     * @param int|float $v
     * @param int|float $percent
     *
     * @return int
     */
    public static function percent($v, $percent, $method = self::FLOOR)
    {
        return self::round($v * $percent / 100, $method);
    }

    /**
     * @param $v
     *
     * @return int
     */
    public static function round($v, $method = self::FLOOR)
    {
        switch ($method) {
            case self::FLOOR:
                return (int)floor($v);
            case self::CEIL:
                return (int)ceil($v);
            default:
                return (int)round($v);
        }
    }

    public function calculate()
    {
        if ($this->options[Option::KEY_BONUS_PER_RUBLES] <= 0
            || $this->options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT] <= 0
            || $this->options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER] <= 0) {
            return;
        }

        if ($this->input->bonus <= 0) {
            return;
        }

        $price = $this->bonusToPrice($this->input->bonus);
        $this->debiting($price);
    }

    /**
     * @param $bonus
     *
     * @return int
     */
    protected function bonusToPrice($bonus)
    {
        $k = $this->options[Option::KEY_BONUS_PER_RUBLES];
        return self::round($bonus * $k, self::FLOOR);
    }

    /**
     * @param $price
     *
     * @return int
     */
    protected function priceToBonus($price)
    {
        $k = $this->options[Option::KEY_BONUS_PER_RUBLES];
        return self::round($price / $k, self::FLOOR);
    }

    /**
     * @param $offer
     *
     * @return int
     */
    protected function maxSpendForOffer($offer)
    {
        $productId = $offer['product_id'];
        if (isset($this->productBonusOptions[$productId][ProductBonusOption::MAX_PERCENTAGE_PAYMENT])) {
            $percent = $this->productBonusOptions[$productId][ProductBonusOption::MAX_PERCENTAGE_PAYMENT];
            return self::percent($offer['price'], $percent);
        }

        return self::percent($offer['price'], $this->options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT]);
    }

    /**
     * @return Collection
     */
    protected function sortOffers()
    {
        return $this->input->offers->sortBy(function ($offer) {
           return $this->maxSpendForOffer($offer);
        })->keys();
    }

    /**
     * @param int $price
     */
    protected function debiting(int $price)
    {
        $priceOrder = $this->input->getPriceOrders();
        $maxSpendForOrder = self::percent($priceOrder, $this->options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER]);
        $spendForOrder = min($price, $maxSpendForOrder);

        $offerIds = $this->sortOffers();
        foreach ($offerIds as $offerId) {
            $offer = $this->input->offers[$offerId];
            $maxSpendForOffer = $this->maxSpendForOffer($offer);

            $offerPrice       = $offer['price'];
            $spendForOffer    = self::percent($spendForOrder, $offerPrice / $priceOrder * 100, self::ROUND);
            $changePriceValue = min($maxSpendForOffer, $spendForOffer);
            if ($spendForOrder < $changePriceValue * $offer['qty']) {
                $spendForOffer    = self::percent($spendForOrder, $offerPrice / $priceOrder * 100, self::FLOOR);
                $changePriceValue = min($maxSpendForOffer, $spendForOffer);
            }

            $discount = $this->changePrice(
                $offer,
                $changePriceValue,
                Discount::DISCOUNT_VALUE_TYPE_RUB,
                true,
                self::LOWEST_POSSIBLE_PRICE
            );

            $spendForOrder -= $discount * $offer['qty'];
            $priceOrder    -= $offerPrice * $offer['qty'];

            $offer['bonusSpent'] = self::priceToBonus($discount);
            $offer['bonusDiscount'] = $discount;
        }
    }
}
