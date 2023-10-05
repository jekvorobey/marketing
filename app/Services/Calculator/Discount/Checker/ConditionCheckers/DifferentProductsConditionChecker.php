<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\DiscountConditionStore;

class DifferentProductsConditionChecker extends AbstractConditionChecker
{
    public const STORE_KEY = 'different_products';

    /**
     * @return bool
     */
    public function check(): bool
    {
        $differentProductsCount = $this->input
            ->basketItems
            ->groupBy('product_id')
            ->count();

        $success = $differentProductsCount >= $this->condition->getCount();

        if ($success) {
            $this->saveConditionAdditionalAmount();
        }

        return $success;
    }

    /**
     * Сохраняем в стор, чтобы потом добавить дополнительную скидку при необходимости
     * @return void
     */
    private function saveConditionAdditionalAmount(): void
    {
        /** @var DiscountCondition|null $savedCondition */
        $savedCondition = DiscountConditionStore::get(self::STORE_KEY);

        /** Если там уже есть условие из этой скидки с большим числом товаров */
        if ($savedCondition && $savedCondition->getCount() > $this->condition->getCount()) {
            return;
        }

        DiscountConditionStore::put(self::STORE_KEY, $this->condition);
    }
}
