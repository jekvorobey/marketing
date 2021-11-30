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
        $offerProductIds = $this->input->offers->pluck('product_id')->unique()->values();

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
    abstract protected function needCalculateBonus(): bool;

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
        if (!$this->needCalculateBonus()) {
            return;
        }

        $offers = $this->prepareOffers();

        $bonusPriceRemains = $this->bonusToPrice(
            $this->getBonusesForSpend()
        );

        foreach ($offers as $offer) {
            $spendForOfferItem = $this->getSpendBonusPriceForOfferItem($offer, $bonusPriceRemains);

            $offerId = $offer['offer_id'] ?? null;
            if ($bundleId = $offer['bundle_id'] ?? null) {
                $inputOffer = &$this->input->offers[$offerId]['bundles'][$bundleId];
            } else {
                $inputOffer = &$this->input->offers[$offerId];
            }

            $bonusPriceRemains -= $this->spentBonusForOffer($inputOffer, $spendForOfferItem, $offer['qty']);

            if ($bonusPriceRemains <= 0) {
                break;
            }
        }
    }

    /**
     * Подготовить данные по оферам для вычисления
     */
    private function prepareOffers(): Collection
    {
        return $this->sortOffers(
            $this->transformOffers()
        );
    }

    /**
     * Преобразовать данные по оферам
     */
    private function transformOffers(): Collection
    {
        $items = collect();

        $this->input->offers->each(function ($offer) use ($items) {
            foreach ($offer['bundles'] as $id => $bundle) {
                $items->push([
                    'offer_id' => $offer['id'],
                    'product_id' => $offer['product_id'],
                    'qty' => $bundle['qty'],
                    'price' => $id == 0 ? $offer['price'] : $bundle['price'],
                    'bundle_id' => $this->input->bundles->contains($id) ? $id : null,
                    'has_discount' => isset($offer['discount']) && $offer['discount'] > 0,
                ]);
            }
        });

        return $items;
    }

    /**
     * Сортировать данные по оферам:
     * - сначала с меньшим кол-во, чтобы бонусы лучше делились
     * - потом с большей стоимости ед.товара, чтобы распределять бонусы по меньшему кол-во позиций в заказе
     */
    protected function sortOffers(Collection $offers): Collection
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
    protected function maxBonusPriceForOfferItem(array $offer): int
    {
        $productId = $offer['product_id'];
        $percent = $this->getBonusProductOption($productId, ProductBonusOption::MAX_PERCENTAGE_PAYMENT);

        if ($percent === null) {
            $percent = $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT);
        }

        return CalculatorChangePrice::percent($offer['price'], $percent);
    }

    /**
     * Получить макс.размер стоимости ед.товара со скидкой, который можно погасить бонусом
     */
    protected function maxBonusPriceForDiscountOfferItem(array $offer): int
    {
        $productId = $offer['product_id'];
        $percent = $this->getBonusProductOption($productId, ProductBonusOption::MAX_PERCENTAGE_DISCOUNT_PAYMENT);

        if ($percent === null) {
            $percent = $this->getOption(Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_DISCOUNT_PRODUCT);
        }

        return CalculatorChangePrice::percent($offer['price'], $percent);
    }

    /**
     * Высчитать размер стоимости ед.товара, который можно погасить бонусом
     */
    protected function getSpendBonusPriceForOfferItem(array $offer, int $bonusPriceRemains): int
    {
        $maxSpendForOfferItem = !$offer['has_discount']
            ? $this->maxBonusPriceForOfferItem($offer)
            : $this->maxBonusPriceForDiscountOfferItem($offer);

        $spendForOffer = min($bonusPriceRemains, $maxSpendForOfferItem * $offer['qty']);

        return CalculatorChangePrice::round($spendForOffer / $offer['qty']);
    }

    /**
     * Установить кол-во списания бонусов для офера
     */
    abstract protected function spentBonusForOffer(&$offer, int $spendForOfferItem, int $qty): int;

    /**
     * Применить и вернуть размер скидки на офер
     */
    protected function applyDiscountForOffer(&$offer, int $value, int $qty, bool $apply = true): int
    {
        $calculatorChangePrice = new CalculatorChangePrice();
        $changedPrice = $calculatorChangePrice->changePrice(
            $offer,
            $value,
            Discount::DISCOUNT_VALUE_TYPE_RUB,
            CalculatorChangePrice::LOWEST_POSSIBLE_PRICE
        );

        if ($apply) {
            if (isset($changedPrice['discount'])) {
                $offer['discount'] = $changedPrice['discount'];
            }
            if (isset($changedPrice['price'])) {
                $offer['price'] = $changedPrice['price'];
            }
            if (isset($changedPrice['cost'])) {
                $offer['cost'] = $changedPrice['cost'];
            }
        }

        return $calculatorChangePrice::round($changedPrice['discountValue']) * $qty;
    }

    /**
     * Получить возможный размер скидку на офер (без применения)
     */
    protected function getDiscountForOffer($offer, int $value, int $qty): int
    {
        return $this->applyDiscountForOffer($offer, $value, $qty, false);
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
