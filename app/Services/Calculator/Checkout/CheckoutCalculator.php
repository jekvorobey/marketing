<?php

namespace App\Services\Calculator\Checkout;

use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\Bonus\BonusCalculator;
use App\Services\Calculator\Bonus\BonusMayBeSpentCalculator;
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
        $input = new InputCalculator($params);
        $output = new OutputCalculator();

        parent::__construct($input, $output);
    }

    /**
     * Возвращает данные о примененных скидках
     *
     * @return array
     */
    public function calculate(): array
    {
        $calculators = [
            PromoCodeCalculator::class,
            DiscountCalculator::class,
            BonusMayBeSpentCalculator::class,
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
            'discounts' => $this->output->appliedDiscounts->values(),
            'bonuses' => $this->output->appliedBonuses->values(),
            'maxSpendableBonus' => $this->output->maxSpendableBonus ?? 0,
            'basketItems' => $this->getFormatBasketItems(),
            'deliveries' => $this->input->deliveries['items']->values(),
        ];
    }

    public function getFormatBasketItems(): Collection
    {
        return $this->input->basketItems->map(function ($basketItem, $basketItemId) {
            return [
                'id' => $basketItemId,
                'offer_id' => $basketItem['offer_id'],
                'price' => $basketItem['price'],
                'qty' => (float) $basketItem['qty'],
                'cost' => $basketItem['cost'] ?? $basketItem['price'],
                'discount' => $basketItem['discount'] ?? 0,
                'discounts' => $basketItem['discounts'] ?? [],
                'bonusSpent' => $basketItem['bonusSpent'] ?? 0,
                'bonusDiscount' => $basketItem['bonusDiscount'] ?? 0,
                'bonus' => $basketItem['bonus'] ?? 0,
                'bonuses' => $basketItem['bonuses'] ?? collect(),
                'bundle_id' => $basketItem['bundle_id'] ?? 0,
            ];
        });
    }
}
