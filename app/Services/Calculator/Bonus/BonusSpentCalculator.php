<?php

namespace App\Services\Calculator\Bonus;

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
    const DEFAULT_BONUS_PER_RUBLES = 1;
    const DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT = 100;
    const DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_ORDER = 100;

    /**
     * @var array
     */
    protected $options = [];

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

        if ($this->options[Option::KEY_BONUS_PER_RUBLES] <= 0
            || $this->options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT] <= 0
            || $this->options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER] <= 0) {
            return;
        }
    }

    /**
     * @param int|float $v
     * @param int|float $percent
     *
     * @return int
     */
    public static function percent($v, $percent)
    {
        return self::round($v * $percent / 100);
    }

    /**
     * @param $v
     *
     * @return int
     */
    public static function round($v)
    {
        return (int) floor($v);
    }

    public function calculate()
    {
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
        return self::round($bonus * $k);
    }

    /**
     * @param $price
     *
     * @return int
     */
    protected function priceToBonus($price)
    {
        $k = $this->options[Option::KEY_BONUS_PER_RUBLES];
        return self::round($price / $k);
    }

    /**
     * @param $offer
     *
     * @return int
     *
     * @todo максимальный процент от оффера
     */
    protected function maxSpendForOffer($offer)
    {
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
            $spendForOffer    = self::percent($spendForOrder, $offerPrice / $priceOrder * 100);
            $changePriceValue = min($maxSpendForOffer, $spendForOffer);

            $discount = $this->changePrice(
                $offer,
                $changePriceValue,
                Discount::DISCOUNT_VALUE_TYPE_RUB,
                true,
                self::LOWEST_POSSIBLE_PRICE
            );

            if ($discount < $spendForOffer) {
                $spendForOrder -= $discount * $offer['qty'];
                $priceOrder    -= $offerPrice * $offer['qty'];
            }

            $offer['bonusSpent'] = self::priceToBonus($discount);
            $offer['bonusDiscount'] = $discount;
        }
    }
}
