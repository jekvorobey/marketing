<?php

namespace App\Services\Calculator\Checkout;

use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\Bonus\BonusCalculator;
use App\Services\Calculator\Bonus\BonusSpentCalculator;
use App\Services\Calculator\Discount\DiscountCalculator;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;
use App\Services\Calculator\PromoCode\PromoCodeCalculator;
use Illuminate\Support\Collection;
use Pim\Core\PimException;

/**
 * Класс для расчета скидок (цен) для отображения в чекауте
 * Class CheckoutPriceCalculator
 * @package App\Core\Discount
 */
class CheckoutCalculator extends AbstractCalculator
{
    /**
     * DiscountCalculator constructor.
     *
     * @param Collection|array $params
     *      Формат:
     *      {
     *      'customer': ['id' => int],
     *      'offers': [['id' => int, 'qty' => int|null], ...]]
     *      'promoCode': string|null
     *      'deliveries': [['method' => int, 'price' => int, 'region' => int, 'selected' => bool], ...]
     *      'payment': ['method' => int]
     *      }
     *
     * @throws PimException
     */
    public function __construct(Collection $params)
    {
        $input  = new InputCalculator($params);
        $output = new OutputCalculator();
        parent::__construct($input, $output);
    }

    /**
     * Возвращает данные о примененных скидках
     *
     * @return array
     */
    public function calculate()
    {
        $calculators = [
            PromoCodeCalculator::class,
            DiscountCalculator::class,
            BonusSpentCalculator::class,
            BonusCalculator::class,
        ];

        foreach ($calculators as $calculatorName) {
            /** @var AbstractCalculator $calculator */
            $calculator = new $calculatorName($this->input, $this->output);
            $calculator->calculate();
        }

        return [
            'promoCodes' => $this->output->appliedPromoCode ? [$this->output->appliedPromoCode] : [],
            'discounts'  => $this->output->appliedDiscounts->values(),
            'bonuses'    => $this->output->appliedBonuses->values(),
            'offers'     => $this->getFormatOffers(),
            'deliveries' => $this->input->deliveries['items']->values(),
        ];
    }

    /**
     * @return Collection
     */
    public function getFormatOffers()
    {
        return $this->input->offers->map(function ($offer, $offerId) {
            return [
                'offer_id'   => $offerId,
                'price'      => (int)$offer['price'],
                'qty'        => (float)$offer['qty'],
                'cost'       => (int)($offer['cost'] ?? $offer['price']),
                'discount'   => $offer['discount'] ?? 0,
                'discounts'  => $offer['discounts'] ?? [],
                'spentBonus' => $offer['spentBonus'] ?? 0,
                'bonus'      => $offer['bonus'] ?? 0,
                'bonuses'    => $offer['bonuses'] ?? collect(),
            ];
        });
    }
}
