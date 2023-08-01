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
use Greensight\CommonMsa\Dto\RoleDto;
use Illuminate\Support\Collection;

/**
 * Класс для расчета скидок (цен) для отображения в чекауте
 * Class CheckoutPriceCalculator
 * @package App\Core\Discount
 */
class CheckoutCalculator extends AbstractCalculator
{
    /**
     * DiscountCalculator constructor.
     *      Формат:
     *      {
     *      'customer': ['id' => int],
     *      'offers': [['id' => int, 'qty' => int|null], ...]]
     *      'promoCode': string|null
     *      'deliveries': [['method' => int, 'price' => int, 'region' => int, 'selected' => bool], ...]
     *      'payment': ['method' => int]
     *      }
     *
     * @param Collection $params
     */
    public function __construct(Collection $params)
    {
        $input = new InputCalculator($params);
        $output = new OutputCalculator();

        parent::__construct($input, $output);
    }

    /**
     * Возвращает данные о примененных скидках
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

            switch ($this->input->roleId) {
                case RoleDto::ROLE_SHOWCASE_GUEST:
                case RoleDto::ROLE_SHOWCASE_CUSTOMER:
                    $price = $basketItem['price_retail'] ?: $basketItem['price'];
                    $cost = $basketItem['cost'] ?? ($basketItem['price_retail'] ?: $basketItem['price']);
                    break;
                case RoleDto::ROLE_SHOWCASE_PROFESSIONAL:
                case RoleDto::ROLE_SHOWCASE_REFERRAL_PARTNER:
                    $price = $basketItem['price'];
                    $cost = $basketItem['cost'] ?? $basketItem['price'];
                    break;
                default:
                    $price = $basketItem['price'];
                    $cost = $basketItem['cost'] ?? $basketItem['price'];
            }

            return [
                'id' => $basketItemId,
                'offer_id' => $basketItem['offer_id'],
                //'price' => $price,
                'price' => $basketItem['price'],
                'price_prof' => $basketItem['price'] ?? 0,
                'price_base' => $basketItem['price_base'] ?? 0,
                'price_retail' => $basketItem['price_retail'] ?? 0,
                'qty' => (float) $basketItem['qty'],
                //'cost' => $cost,
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
