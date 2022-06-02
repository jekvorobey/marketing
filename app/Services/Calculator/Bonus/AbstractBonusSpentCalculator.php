<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Bonus\ProductBonusOption\ProductBonusOption;
use App\Models\Discount\Discount;
use App\Models\Option\Option;
use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\CalculatorChangePrice;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;
use Illuminate\Support\Collection;

abstract class AbstractBonusSpentCalculator extends AbstractCalculator
{
    private Collection $productBonusOptions;

    public function __construct(InputCalculator $inputCalculator, OutputCalculator $outputCalculator)
    {
        parent::__construct($inputCalculator, $outputCalculator);

        $this->loadProductBonusOptions();
    }

    /**
     * Загрузить опции бонусов всех товаров для текущего input
     */
    private function loadProductBonusOptions(): void
    {
        $offerProductIds = $this->input->basketItems->pluck('product_id')->unique()->values();

        $this->productBonusOptions = ProductBonusOption::query()
            ->whereIn('product_id', $offerProductIds)
            ->pluck('value', 'product_id');
    }

    /**
     * Получить опцию бонусов для указанного товара
     * @return mixed
     */
    protected function getBonusProductOption(?int $productId, string $key)
    {
        if (!$productId) {
            return null;
        }

        return $this->productBonusOptions[$productId][$key] ?? null;
    }

    /**
     * Нужно ли вычислять скидку бонусами
     */
    abstract protected function needCalculate(): bool;

    /**
     * Заданы ли все настройки для списания бонусов
     */
    protected function bonusSettingsIsSet(): bool
    {
        return $this->getOption(Option::KEY_BONUS_PER_RUBLES) > 0
            && $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT) > 0
            && $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER) > 0;
    }

    public function calculate(): void
    {
        if (!$this->needCalculate()) {
            return;
        }

        $basketItems = $this->prepareBasketItems();

        $bonusPriceRemains = $this->bonusToPrice(
            $this->getBonusesForSpend()
        );

        foreach ($basketItems as $basketItem) {
            $spendForBasketItem = $this->getSpendBonusPriceForBasketItem($basketItem, $bonusPriceRemains);
            $inputBasketItem = &$this->input->basketItems[$basketItem['id']];

            $bonusPriceRemains -= $this->spentBonusForBasketItem($inputBasketItem, $spendForBasketItem, $basketItem['qty']);

            if ($bonusPriceRemains <= 0) {
                break;
            }
        }
    }

    /**
     * Подготовить данные по элементам корзины для вычисления
     */
    private function prepareBasketItems(): Collection
    {
        return $this->sortBasketItems(
            $this->transformBasketItems()
        );
    }

    /**
     * Преобразовать данные по элементам корзины
     */
    private function transformBasketItems(): Collection
    {
        $items = collect();

        $this->input->basketItems->each(function ($basketItem) use ($items) {
            $items->push([
                'id' => $basketItem['id'],
                'offer_id' => $basketItem['offer_id'],
                'product_id' => $basketItem['product_id'],
                'qty' => $basketItem['qty'],
                'price' => $basketItem['price'],
                'bundle_id' => $basketItem['bundle_id'],
                'has_discount' => isset($basketItem['discount']) && $basketItem['discount'] > 0,
            ]);
        });

        return $items;
    }

    /**
     * Сортировать данные по элементам корзины:
     * - сначала с меньшим кол-во, чтобы бонусы лучше делились
     * - потом с большей стоимости ед.товара, чтобы распределять бонусы по меньшему кол-во позиций в заказе
     */
    protected function sortBasketItems(Collection $offers): Collection
    {
        // @see https://gist.github.com/matt-allan/4ce3ba62396c3d71241f0da39ddb88e6
        return $offers
            ->sort(fn(array $a, array $b) => [$a['qty'], $b['price']] <=> [$b['qty'], $a['price']])
            ->values();
    }

    /**
     * Общая сумма бонусов для расчета скидок
     */
    abstract protected function getBonusesForSpend(): int;

    /**
     * Получить макс.размер стоимости ед.товара, который можно погасить бонусом
     */
    protected function maxBonusPriceForBasketItem(array $basketItem): int
    {
        $productId = $basketItem['product_id'];
        $percent = $this->getBonusProductOption($productId, ProductBonusOption::MAX_PERCENTAGE_PAYMENT);

        if ($percent === null) {
            $percent = $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT);
        }

        return CalculatorChangePrice::percent($basketItem['price'], $percent);
    }

    /**
     * Получить макс.размер стоимости ед.товара со скидкой, который можно погасить бонусом
     */
    protected function maxBonusPriceForDiscountBasketItem(array $basketItem): int
    {
        $productId = $basketItem['product_id'];
        $percent = $this->getBonusProductOption($productId, ProductBonusOption::MAX_PERCENTAGE_DISCOUNT_PAYMENT);

        if ($percent === null) {
            $percent = $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_DISCOUNT_PRODUCT);
        }

        return CalculatorChangePrice::percent($basketItem['price'], $percent);
    }

    /**
     * Высчитать размер стоимости ед.товара, который можно погасить бонусом
     */
    protected function getSpendBonusPriceForBasketItem(array $basketItem, int $bonusPriceRemains): int
    {
        if ($basketItem['qty'] === 0) {
            return 0;
        }

        $maxSpendForOfferItem = !$basketItem['has_discount']
            ? $this->maxBonusPriceForBasketItem($basketItem)
            : $this->maxBonusPriceForDiscountBasketItem($basketItem);

        $spendForOffer = min($bonusPriceRemains, $maxSpendForOfferItem * $basketItem['qty']);

        return CalculatorChangePrice::round($spendForOffer / $basketItem['qty']);
    }

    /**
     * Установить кол-во списания бонусов для элемента корзины
     */
    abstract protected function spentBonusForBasketItem(&$basketItem, int $spendForBasketItem, int $qty): int;

    /**
     * Применить и вернуть размер скидки на офер
     */
    protected function applyDiscountForBasketItem(&$basketItem, int $value, int $qty, bool $apply = true): int
    {
        $calculatorChangePrice = new CalculatorChangePrice();
        $changedPrice = $calculatorChangePrice->changePrice(
            $basketItem,
            $value,
            Discount::DISCOUNT_VALUE_TYPE_RUB,
            CalculatorChangePrice::LOWEST_POSSIBLE_PRICE
        );

        if ($apply) {
            $basketItem = $calculatorChangePrice->syncItemWithChangedPrice($basketItem, $changedPrice);
        }

        return $calculatorChangePrice::round($changedPrice['discountValue']) * $qty;
    }

    /**
     * Получить возможный размер скидку на элемент корзины (без применения)
     */
    protected function getDiscountForBasketItem($basketItem, int $value, int $qty): int
    {
        return $this->applyDiscountForBasketItem($basketItem, $value, $qty, false);
    }

    /**
     * Конвертировать бонусы в рубли
     */
    protected function bonusToPrice(int $bonus): int
    {
        $bonusPerRub = $this->getOption(Option::KEY_BONUS_PER_RUBLES);

        return CalculatorChangePrice::round($bonus * $bonusPerRub);
    }

    /**
     * Конвертировать рубли в бонусы
     */
    protected function priceToBonus(int $price): int
    {
        $bonusPerRub = $this->getOption(Option::KEY_BONUS_PER_RUBLES);

        if (!$bonusPerRub) {
            return 0;
        }

        return CalculatorChangePrice::round($price / $bonusPerRub);
    }
}
