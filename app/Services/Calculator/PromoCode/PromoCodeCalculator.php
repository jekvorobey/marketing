<?php

namespace App\Services\Calculator\PromoCode;

use App\Models\Discount\Discount;
use App\Models\PromoCode\PromoCode;
use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\Bonus\BonusCalculator;
use App\Services\Calculator\CalculatorChangePrice;
use App\Services\Calculator\Discount\Calculators\DiscountCalculator;
use App\Services\Calculator\PromoCode\Dto\PromoCodeResult;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Oms\Services\OrderService\OrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
                'is_birthday_promo' => $this->promoCode->is_birthday_promo,
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
            /* убираем скидки на доставку, чтобы на чекауте не путать клиентов */
            $noDeliveryPromocodeDiscounts = $promocodeDiscounts->reject(
                fn ($d) => $d->type === Discount::DISCOUNT_TYPE_DELIVERY
            );
            $change = $this->output
                ->appliedDiscounts
                ->filter(
                    fn ($appliedDiscount) => $noDeliveryPromocodeDiscounts->pluck('id')->contains($appliedDiscount['id'])
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
    protected function checkPromoCodeConditions(): bool
    {
        if (empty($this->promoCode->conditions)) {
            return true;
        }

        if ($this->promoCode->is_birthday_promo && !$this->checkBirthdayPromoConditions()) {
            return false;
        }

        $roleIds = collect($this->promoCode->getRoleIds());
        if ($roleIds->isNotEmpty() && $roleIds->intersect($this->input->customer['roles'])->isEmpty()) {
            return false;
        }

        $customerIds = $this->promoCode->getCustomerIds();
        if (!empty($customerIds) && !in_array($this->input->customer['id'], $customerIds)) {
            return false;
        }
        $segmentIds = $this->promoCode->getSegmentIds();

        return empty($segmentIds) || in_array($this->input->customer['segment'], $segmentIds);
    }

    /**
     * Проверяет ограничение на количество применений одного промокода
     */
    protected function checkPromoCodeCounter(): bool
    {
        if (!isset($this->promoCode->counter)) {
            return true;
        }

        /** @var OrderService $orderService */
        $orderService = resolve(OrderService::class);
        switch ($this->promoCode->type_of_limit) {
            case PromoCode::TYPE_OF_LIMIT_USER:
                $customerId = $this->input->getCustomerId();
                if (!$customerId) {
                    return true;
                }

                return $this->promoCode->counter > $orderService->orderPromoCodeCountByCustomer($this->promoCode->id, $customerId);
            case PromoCode::TYPE_OF_LIMIT_ALL:
                return $this->promoCode->counter > $orderService->orderPromoCodeCount($this->promoCode->id);
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

        $check = $this->checkPromoCodeConditions() && $this->checkPromoCodeCounter();
        if (!$check) {
            $this->promoCode = null;
        }

        return $this;
    }

    /**
     * Убираем все скидки, в которых есть пользователь и он не текущий
     * (Не учитываются логические операторы)
     * @return Discount[]|Collection
     */
    protected function rejectOtherCustomersDiscounts(): Collection
    {
        $discountQuery = Discount::query()
            ->select([
                DB::raw('discounts.id AS id'),
            ])
            ->join(DB::raw('discount_conditions'), function($join) {
                $join->on('discounts.id', '=', 'discount_conditions.discount_id');
            })
            ->join(DB::raw('discount_promo_code'), function($join) {
                $join->on('discounts.id', '=', 'discount_promo_code.discount_id');
            })
            ->join(DB::raw('promo_codes'), function($join) {
                $join->on('discount_promo_code.promo_code_id', '=', 'promo_codes.id');
            })
            ->where('promo_codes.code', '=', PromoCode::HAPPY2U_PROMOCODE)
            ->where('discount_conditions.condition', 'LIKE', "%customerIds%{$this->input->getCustomerId()}%");

        $discounts = $discountQuery->get();
        $discountIds = $discounts->pluck('id')->unique()->all();

        return Discount::query()->whereIn('id', $discountIds)->get();
    }

    private function checkBirthdayPromoConditions(): bool
    {
        /** @var CustomerDto $customer */
        $customer = $this->getCustomer();

        if (!$customer || !$customer->birthday) {
            return false;
        }

        if (!$this->isYearPastAfterLastUse($customer->birthday_promo_used_at)) {
            return false;
        }

        $birthday = Carbon::parse($customer->birthday)->setYear(now()->year);

        $dateFrom = (clone $birthday)->subDays(
            $this->promoCode->getDaysBeforeBirthday()
        );
        $dateTo = (clone $birthday)->addDays(
            $this->promoCode->getDaysAfterBirthday()
        );

        if (!(now()->isBetween($dateFrom, $dateTo))) {
            return false;
        }

        return true;
    }

    private function getCustomer(): ?CustomerDto
    {
        $customerService = resolve(CustomerService::class);

        return $customerService->customers(
            $customerService->newQuery()->setFilter('id', $this->input->getCustomerId())
        )->first() ?? null;
    }

    private function isYearPastAfterLastUse(?string $birthdayPromoUsedAt): bool
    {
        if (!$birthdayPromoUsedAt) {
            return true;
        }

        $diffInDays = Carbon::parse($birthdayPromoUsedAt)->diffInDays(now());

        return $diffInDays >
            Carbon::DAYS_PER_YEAR - (
                $this->promoCode->getDaysBeforeBirthday() + $this->promoCode->getDaysAfterBirthday()
            );
    }
}
