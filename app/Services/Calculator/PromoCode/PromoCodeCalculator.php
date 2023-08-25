<?php

namespace App\Services\Calculator\PromoCode;

use App\Models\Discount\Discount;
use App\Models\PromoCode\PromoCode;
use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\Bonus\BonusCalculator;
use App\Services\Calculator\CalculatorChangePrice;
use App\Services\Calculator\Discount\DiscountCalculator;
use App\Services\Calculator\PromoCode\Dto\PromoCodeResult;
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
    protected $promoCode;

    public function calculate(): void
    {
        if (!$this->needCalculate()) {
            return;
        }

        $this->output->appliedPromoCode = $this->fetchPromoCode()->apply();
    }

    protected function needCalculate(): bool
    {
        return $this->input->payment['isNeedCalculate'];
    }

    /**
     * Применяет промокоды
     */
    protected function apply(): ?array
    {
        if (!$this->promoCode) {
            return null;
        }

        $promocodeResult = match($this->promoCode->type) {
            PromoCode::TYPE_DISCOUNT => $this->applyDiscountPromocode(),
            PromoCode::TYPE_DELIVERY => $this->applyDeliveryPromocode(),
            PromoCode::TYPE_BONUS => $this->applyBonusPromocode(),
            default => PromoCodeResult::notApplied()
        };

        return $promocodeResult->isApplied()
            ? [
                'id' => $this->promoCode->id,
                'type' => $this->promoCode->type,
                'status' => $this->promoCode->status,
                'name' => $this->promoCode->name,
                'code' => $this->promoCode->code,
                'discounts' => $this->promoCode->getDiscountIds(),
                'gift_id' => $this->promoCode->gift_id,
                'bonus_id' => $this->promoCode->bonus_id,
                'owner_id' => $this->promoCode->owner_id,
                'change' => $promocodeResult->getChange(),
            ] : null;
    }

    /**
     * Применить промокод типа СКИДКА
     * @return PromoCodeResult
     */
    private function applyDiscountPromocode(): PromoCodeResult
    {
        $promocodeDiscounts = $this->promoCode->discounts;

        if ($promocodeDiscounts->isEmpty()) {
            return PromoCodeResult::notApplied();
        }

        $this->input->promoCodeDiscounts = $promocodeDiscounts;

        $discountCalculator = new DiscountCalculator($this->input, $this->output);
        $discountCalculator->calculate();

        if ($this->output->anyDiscountWasApplied()) {
            $change = $this->output
                ->appliedDiscounts
                ->filter(
                    fn($appliedDiscount) => $promocodeDiscounts->pluck('id')->contains($appliedDiscount['id'])
                )
                ->sum('change');

            $discountCalculator->forceRollback();
        } else {
            $calc = new CalculatorChangePrice();
            $change = $promocodeDiscounts->reduce(function(?float $sum, Discount $discount) use ($calc) {
                $sum + $calc->calculateDiscountByType(
                    $this->input->basketItems->sum('price'),
                    $discount->value,
                    $discount->value_type
                );
            }, 0);
        }

        return PromoCodeResult::applied($change);
    }

    /**
     * @return PromoCodeResult
     */
    public function applyDeliveryPromocode(): PromoCodeResult
    {
        // Мерчант не может изменять стоимость доставки
        if ($this->promoCode->merchant_id) {
            return PromoCodeResult::notApplied();
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
                if ($delivery['selected']) {
                    $change += $changeForDelivery;
                }
                $this->input->freeDelivery = true;
                $this->input->deliveries['items'][$k] = $delivery;
            }
        }

        return PromoCodeResult::applied($change);
    }

    /**
     * @return PromoCodeResult
     */
    public function applyBonusPromocode(): PromoCodeResult
    {
        $bonus = $this->promoCode->bonus;

        if (!$bonus) {
            return PromoCodeResult::notApplied();
        }

        $this->input->promoCodeBonus = $bonus;
        $bonusCalculator = new BonusCalculator($this->input, $this->output);
        $bonusCalculator->calculate();
        $outputBonus = $this->output
            ->appliedBonuses
            ->filter(function ($item) use ($bonus) {
                return $item['id'] === $bonus->id;
            })
            ->first();

        return $outputBonus
            ? PromoCodeResult::applied(null)
            : PromoCodeResult::notApplied();
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
