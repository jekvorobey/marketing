<?php

namespace App\Services\Calculator\PromoCode;

use App\Models\Discount\Discount;
use App\Models\PromoCode\PromoCode;
use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\Bonus\BonusCalculator;
use App\Services\Calculator\CalculatorChangePrice;
use App\Services\Calculator\Discount\DiscountCalculator;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;
use Greensight\Oms\Dto\Payment\PaymentMethod;
use Greensight\Oms\Services\OrderService\OrderService;

/**
 * Class PromoCodeCalculator
 * @package App\Services\Calculator\PromoCode
 */
class PromoCodeCalculator extends AbstractCalculator
{
    /**
     * Список промокодов
     * @var PromoCode|null
     */
    protected $promoCode = null;

    public function __construct(InputCalculator $inputCalculator, OutputCalculator $outputCalculator)
    {
        parent::__construct($inputCalculator, $outputCalculator);
    }

    public function calculate()
    {
        if ($this->input->payment['method'] === PaymentMethod::CREDITPAYMENT) {
            return;
        }
        $this->output->appliedPromoCode = $this->fetchPromoCode()->apply();
    }

    /**
     * Применяет промокоды
     */
    protected function apply(): ?array
    {
        if (!$this->promoCode) {
            return null;
        }

        $change = null;
        $isApply = false;
        switch ($this->promoCode->type) {
            case PromoCode::TYPE_DISCOUNT:
                $discount = $this->promoCode->discount;
                if (!$discount) {
                    break;
                }

                $this->input->promoCodeDiscount = $discount;
                $discountCalculator = new DiscountCalculator($this->input, $this->output);
                $discountCalculator->calculate();
                if ($this->output->appliedDiscounts->count() != 0) {
                    $outputDiscount = $this->output->appliedDiscounts->filter(function ($item) use ($discount) {
                        return $item['id'] === $discount->id;
                    })->first();

                    $isApply = !empty($outputDiscount);
                    $change = $isApply ? $outputDiscount['change'] : 0;
                    $discountCalculator->forceRollback();
                } else {
                    $isApply = true;
                    $calculatorChangePrice = new CalculatorChangePrice();
                    $change = $calculatorChangePrice->calculateDiscountByType(
                        $this->input->basketItems->sum('price'),
                        $discount->value,
                        $discount->value_type
                    );
                }
                break;
            case PromoCode::TYPE_DELIVERY:
                // Мерчант не может изменять стоимость доставки
                if ($this->promoCode->merchant_id) {
                    break;
                }

                $change = 0;
                foreach ($this->input->deliveries['items'] as $k => $delivery) {
                    $calculatorChangePrice = new CalculatorChangePrice();
                    $changedPrice = $calculatorChangePrice->changePrice(
                        $delivery,
                        self::HIGHEST_POSSIBLE_PRICE_PERCENT,
                        Discount::DISCOUNT_VALUE_TYPE_PERCENT,
                        CalculatorChangePrice::FREE_DELIVERY_PRICE
                    );
                    $changeForDelivery = $changedPrice['discountValue'];
                    $delivery = $calculatorChangePrice->syncItemWithChangedPrice($delivery, $changedPrice);

                    if ($changeForDelivery > 0) {
                        $isApply = $changeForDelivery > 0;
                        if ($delivery['selected']) {
                            $change += $changeForDelivery;
                        }
                        $this->input->freeDelivery = true;
                        $this->input->deliveries['items'][$k] = $delivery;
                    }
                }
                $isApply = true; // нужно, чтобы промокод применялся в корзине
                break;
            case PromoCode::TYPE_GIFT:
                // todo
                break;
            case PromoCode::TYPE_BONUS:
                $bonus = $this->promoCode->bonus;
                if (!$bonus) {
                    break;
                }

                $this->input->promoCodeBonus = $bonus;
                $bonusCalculator = new BonusCalculator($this->input, $this->output);
                $bonusCalculator->calculate();
                $outputBonus = $this->output->appliedBonuses->filter(function ($item) use ($bonus) {
                    return $item['id'] === $bonus->id;
                })->first();

                $isApply = !empty($outputBonus);
                break;
        }

        return $isApply
            ? [
                'id' => $this->promoCode->id,
                'type' => $this->promoCode->type,
                'status' => $this->promoCode->status,
                'name' => $this->promoCode->name,
                'code' => $this->promoCode->code,
                'discount_id' => $this->promoCode->discount_id,
                'gift_id' => $this->promoCode->gift_id,
                'bonus_id' => $this->promoCode->bonus_id,
                'owner_id' => $this->promoCode->owner_id,
                'change' => $change,
            ] : null;
    }

    /**
     * Проверяет ограничения заданные в conditions
     */
    protected function checkPromoCodeConditions(PromoCode $promoCode): bool
    {
        if (empty($promoCode->conditions)) {
            return true;
        }

        $roleIds = collect($promoCode->getRoleIds());
        if ($roleIds->isNotEmpty() && $roleIds->intersect($this->input->customer['roles'])->isEmpty()) {
            return false;
        }

        $customerIds = $promoCode->getCustomerIds();
        if (!empty($customerIds) && !in_array($this->input->customer['id'], $customerIds)) {
            return false;
        }
        $segmentIds = $promoCode->getSegmentIds();

        return empty($segmentIds) || in_array($this->input->customer['segment'], $segmentIds);
    }

    /**
     * Проверяет ограничение на количество применений одного промокода
     */
    protected function checkPromoCodeCounter(PromoCode $promoCode): bool
    {
        if (!isset($promoCode->counter)) {
            return true;
        }

        /** @var OrderService $orderService */
        $orderService = resolve(OrderService::class);
        switch ($promoCode->type_of_limit) {
            case PromoCode::TYPE_OF_LIMIT_USER:
                $customerId = $this->input->getCustomerId();
                if (!$customerId) {
                    return false;
                }

                return $promoCode->counter > $orderService->orderPromoCodeCountByCustomer($promoCode->id, $customerId);
            case PromoCode::TYPE_OF_LIMIT_ALL:
                return $promoCode->counter > $orderService->orderPromoCodeCount($promoCode->id);
            default:
                return false;
        }
    }

    /**
     * Получить активный промокод (с кодом $this->input->promoCode)
     */
    protected function fetchPromoCode(): self
    {
        if (!$this->input->promoCode) {
            return $this;
        }

        $this->promoCode = PromoCode::query()
            ->active()
            ->where('code', $this->input->promoCode)
            ->first();

        if (!$this->promoCode) {
            return $this;
        }

        $check = $this->checkPromoCodeConditions($this->promoCode) && $this->checkPromoCodeCounter($this->promoCode);
        if (!$check) {
            $this->promoCode = null;
        }

        return $this;
    }
}
