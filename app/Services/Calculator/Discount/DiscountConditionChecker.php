<?php

namespace App\Services\Calculator\Discount;

use App\Services\Calculator\InputCalculator;
use Illuminate\Support\Collection;
use App\Models\Discount\DiscountCondition as DiscountConditionModel;

class DiscountConditionChecker
{
    protected InputCalculator $input;

    public function __construct(InputCalculator $inputCalculator)
    {
        $this->input = $inputCalculator;
    }

    /**
     * Проверяет доступность применения скидки на все соответствующие условия
     */
    public function check(Collection $conditions, array $checkingConditionTypes = []): bool
    {
        /** @var DiscountConditionModel $condition */
        foreach ($conditions as $condition) {
            if (!$this->checkByType($condition, $checkingConditionTypes)) {
                return false;
            }
        }

        return true;
    }

    private function checkByType(DiscountConditionModel $condition, array $checkingConditionTypes = []): bool
    {
        if (!in_array($condition->type, $checkingConditionTypes)) {
            return false;
        }

        switch ($condition->type) {
            /** Скидка на первый заказ */
            case DiscountConditionModel::FIRST_ORDER:
                return $this->input->getCustomerOrdersCount() === 0;
            /** Скидка на заказ от заданной суммы */
            case DiscountConditionModel::MIN_PRICE_ORDER:
                return $this->input->getCostOrders() >= $condition->getMinPrice();
            /** Скидка на заказ от заданной суммы на один из брендов */
            case DiscountConditionModel::MIN_PRICE_BRAND:
                return $this->input->getMaxTotalPriceForBrands($condition->getBrands()) >= $condition->getMinPrice();
            /** Скидка на заказ от заданной суммы на одну из категорий */
            case DiscountConditionModel::MIN_PRICE_CATEGORY:
                return $this->input->getMaxTotalPriceForCategories($condition->getCategories()) >= $condition->getMinPrice();
            /** Скидка на заказ определенного количества товара */
            case DiscountConditionModel::EVERY_UNIT_PRODUCT:
                return $this->checkEveryUnitProduct($condition->getOffer(), $condition->getCount());
            /** Скидка на один из методов оплаты */
            case DiscountConditionModel::PAY_METHOD:
                return $this->checkPayMethod($condition->getPaymentMethods());
            /** Скидка при заказе из региона */
            case DiscountConditionModel::REGION:
                return $this->checkRegion($condition->getRegions());
            /** Скидка для определенных покупателей */
            case DiscountConditionModel::CUSTOMER:
                return in_array($this->input->getCustomerId(), $condition->getCustomerIds());
            /** Скидка на каждый N-й заказ */
            case DiscountConditionModel::ORDER_SEQUENCE_NUMBER:
                $countOrders = $this->input->getCustomerOrdersCount();
                return isset($countOrders) && (($countOrders + 1) % $condition->getOrderSequenceNumber() === 0);
            case DiscountConditionModel::BUNDLE:
                return true; // todo
            case DiscountConditionModel::DISCOUNT_SYNERGY: // Проверяется отдельно на этапе применения скидок
            case DiscountConditionModel::DELIVERY_METHOD: // Проверяется отдельно в DeliveryApplier, т.к. надо рассчитывать возможные скидки для всех способов доставки
                return true;
            default:
                return false;
        }
    }

    /**
     * Количество единиц одного оффера
     */
    public function checkEveryUnitProduct($offerId, $count): bool
    {
        $basketItemByOfferId = $this->input->basketItems->where('offer_id', $offerId)->where('bundle_id', 0)->first();
        return $basketItemByOfferId && $basketItemByOfferId['qty'] >= $count;
    }

    /**
     * Способ оплаты
     */
    public function checkPayMethod($payments): bool
    {
        return isset($this->input->payment['method']) && in_array($this->input->payment['method'], $payments);
    }

    /**
     * Регион пользователя
     */
    public function checkRegion($regions): bool
    {
        return in_array($this->input->getUserRegionId(), $regions);
    }
}
