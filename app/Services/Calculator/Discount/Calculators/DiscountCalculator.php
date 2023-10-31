<?php

namespace App\Services\Calculator\Discount\Calculators;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountCondition as DiscountConditionModel;
use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\Discount\Applier\BasketApplier;
use App\Services\Calculator\Discount\Applier\DeliveryApplier;
use App\Services\Calculator\Discount\Applier\OfferApplier;
use App\Services\Calculator\Discount\Checker\DiscountChecker;
use App\Services\Calculator\Discount\DiscountFetcher;
use App\Services\Calculator\Discount\DiscountOutput;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;
use Illuminate\Support\Collection;

/**
 * Class DiscountCalculator
 * @package App\Services\Calculator\Discount
 * @test Tests\Feature\DiscountCalculatorTest
 */
class DiscountCalculator extends AbstractCalculator
{
    /** Скидки, которые активированы с помощью промокода */
    protected Collection $appliedDiscounts;

    /** Список скидок, которые можно применить (отдельно друг от друга) */
    protected Collection $possibleDiscounts;

    /** Список активных скидок */
    protected Collection $discounts;

    /**
     * Офферы со скидками в формате:
     * [
     *      basket_item_id => [
     *          [
     *              'id' => discount_id,
     *              'value' => value,
     *              'value_type' => value_type
     *          ],
     *          ...
     *      ]
     * ]
     */
    protected Collection $basketItemsByDiscounts;

    /**
     * DiscountCalculator constructor.
     */
    public function __construct(InputCalculator $inputCalculator, OutputCalculator $outputCalculator)
    {
        parent::__construct($inputCalculator, $outputCalculator);

        $this->discounts = collect();
        $this->appliedDiscounts = collect();
        $this->possibleDiscounts = collect();
        $this->basketItemsByDiscounts = collect();
    }

    /**
     * @return void
     */
    public function calculate(): void
    {
        if (!$this->needCalculate()) {
            return;
        }

        $this->fetchDiscounts();

        if ($this->input->hasDeliveries()) {
            if (!$this->input->freeDelivery) {
                $this->filter()->sort()->apply()->rollback();
            }
            $this->input->setSelectedDelivery();
        }

        // Считаются окончательные скидки + бонусы
        $this->filter()->sort()->apply();

        $this->getDiscountOutput();
    }

    /**
     * Фильтрует все актуальные скидки и оставляет только те, которые можно применить
     * @return $this
     */
    protected function filter(): self
    {
        $this->possibleDiscounts = $this->discounts
            ->filter(function (Discount $discount) {
                $checker = new DiscountChecker($this->input, $discount);
                $checker->exceptConditionTypes($this->getExceptingConditionTypes());
                return $checker->check();
            })
            ->values();

        return $this->compileSynegry();
    }

    /**
     * Сортируем в порядке:
     * 1) бандлы
     * 2) скидка по промо-коду
     * 3) скидка на товар
     * 4) скидка на корзину
     * 5) скидка на доставку
     */
    protected function sort(): self
    {
        /** @var Collection|Discount[] $discounts */
        $discounts = $this->possibleDiscounts->sortBy(
            fn (Discount $discount) => $discount->value_type === Discount::DISCOUNT_VALUE_TYPE_RUB
        );
        $promocodeDiscounts = $this->input->promoCodeDiscounts;

        if ($promocodeDiscounts->isNotEmpty()) {
            [$appliedPromocodeDiscounts, $discounts] = $discounts->partition(
                fn(Discount $discount) => $promocodeDiscounts->pluck('id')->contains($discount->id)
            );
        }
        [$deliveryDiscounts, $discounts] = $discounts->partition('type', Discount::DISCOUNT_TYPE_DELIVERY);
        [$promocodeDiscounts, $discounts] = $discounts->partition('promo_code_only', true);
        [$bundleDiscounts, $discounts] = $discounts->partition(
            fn($discount) => in_array($discount->type, [Discount::DISCOUNT_TYPE_BUNDLE_OFFER, Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS])
        );
        [$cartTotalDiscounts, $discounts] = $discounts->partition('type', Discount::DISCOUNT_TYPE_CART_TOTAL);
        [$nonCatalogDiscounts, $catalogDiscounts] = $discounts->partition(function (Discount $discount) {
            return $discount->conditions->where('type', '!=', DiscountConditionModel::DISCOUNT_SYNERGY)->isNotEmpty();
        });

        $this->possibleDiscounts = $bundleDiscounts
            ->merge($appliedPromocodeDiscounts ?? [])
            ->merge($catalogDiscounts)
            ->merge($promocodeDiscounts)
            ->merge($nonCatalogDiscounts)
            ->merge($cartTotalDiscounts)
            ->merge($deliveryDiscounts);

        // сортируем скидки таким образом, чтобы первыми были скидки с максимальным приоритетом,
        // а затем скидки по убыванию выгодности для клиента
        $this->possibleDiscounts = $this->sortDiscountsByProfit($this->possibleDiscounts)->values();
        $discountsWithoutPriority = $this->possibleDiscounts->where('max_priority', 0);
        $discountsWithPriority = $this->possibleDiscounts->where('max_priority', 1);
        $this->possibleDiscounts = $discountsWithPriority->concat($discountsWithoutPriority);

        return $this;
    }


    /**
     * Применяет скидки
     * @return $this
     */
    protected function apply(): self
    {
        /* скидки на доставку применяются отдельно */
        $possibleDiscounts = $this->possibleDiscounts
            ->where('type', '!=', Discount::DISCOUNT_TYPE_DELIVERY);

        /** @var Discount $discount */
        foreach ($possibleDiscounts as $discount) {
            $this->applyDiscount($discount);
        }

        $this->applyDeliveryDiscounts();

        return $this->processFreeProducts();
    }

    /**
     * Применить скидку
     * @param Discount $discount
     * @return float|false
     */
    protected function applyDiscount(Discount $discount): float|false
    {
        if (!$this->isCompatibleDiscount($discount)) {
            return false;
        }

        //TODO: resolver and classes
        $change = match ($discount->type) {
            Discount::DISCOUNT_TYPE_OFFER => $this->applyDiscountTypeOffer($discount),
            Discount::DISCOUNT_TYPE_ANY_OFFER => $this->applyDiscountTypeAnyOffer($discount),
            Discount::DISCOUNT_TYPE_BUNDLE_OFFER,
            Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS,
            Discount::DISCOUNT_TYPE_ANY_BUNDLE => $this->applyDiscountTypeBundle($discount),
            Discount::DISCOUNT_TYPE_BRAND,
            Discount::DISCOUNT_TYPE_ANY_BRAND => $this->applyDiscountTypeBrand($discount),
            Discount::DISCOUNT_TYPE_CATEGORY,
            Discount::DISCOUNT_TYPE_ANY_CATEGORY => $this->applyDiscountTypeCategory($discount),
            Discount::DISCOUNT_TYPE_DELIVERY => $this->applyDiscountTypeDelivery($discount),
            Discount::DISCOUNT_TYPE_CART_TOTAL => $this->applyDiscountTypeCartTotal($discount),
            Discount::DISCOUNT_TYPE_MASTERCLASS => $this->applyDiscountTypePublicEvent($discount),
            Discount::DISCOUNT_TYPE_ANY_MASTERCLASS => $this->applyDiscountTypeAnyPublicEvent($discount),
            default => false
        };

        $change = $change ?? false;

        /*
         * Добавляем все скидку к примененным.
         * Даже если не повлияла на цену, т.к. она тоже участвовала в подсчете общей скидки
         */
        $this->addDiscountToApplied($discount, $change);

        return $change;
    }

    /**
     * Для определенных офферов
     * @param Discount $discount
     * @return float|null
     */
    public function applyDiscountTypeOffer(Discount $discount): ?float
    {
        $offerIds = $discount->offers->pluck('offer_id');
        return $this->applyDiscountToOffers($discount, $offerIds);
    }

    /**
     * Для всех офферов кроме
     * @param Discount $discount
     * @return float|null
     */
    public function applyDiscountTypeAnyOffer(Discount $discount): ?float
    {
        $exceptOfferIds = $this->getExceptOffersForDiscount($discount);
        $offerIds = $this->input->basketItems
            ->where('product_id', '!=', null)
            ->whereNotIn('offer_id', $exceptOfferIds)
            ->pluck('offer_id');

        return $this->applyDiscountToOffers($discount, $offerIds);
    }

    /**
     * Для бандлов
     * @param Discount $discount
     * @return float|int|null
     */
    public function applyDiscountTypeBundle(Discount $discount): float|int|null
    {
        $bundleItems = $discount->bundleItems;

        if (
            in_array($discount->type, [
                Discount::DISCOUNT_TYPE_BUNDLE_OFFER,
                Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS
            ])
        ) {
            $offerIds = $bundleItems->pluck('item_id');
        } else {
            $exceptBundleIds = $discount->bundles->pluck('bundle_id');
            $offerIds = $this->input
                ->basketItems
                ->filter(function ($basketItem) use ($exceptBundleIds) {
                    return $basketItem['bundle_id'] > 0 && !$exceptBundleIds->contains($basketItem['bundle_id']);
                })
                ->pluck('offer_id');
        }

        if (in_array($discount->type, [Discount::DISCOUNT_TYPE_BUNDLE_OFFER, Discount::DISCOUNT_TYPE_ANY_BUNDLE])) {
            return $this->applyDiscountToOffers($discount, $offerIds);
        }

        return 0;
    }

    /**
     * Для определенных брендов или для всех брендов
     * @param Discount $discount
     * @return float|null
     */
    public function applyDiscountTypeBrand(Discount $discount): ?float
    {
        /** @var Collection $brandIds */
        $brandIds = $discount->type == Discount::DISCOUNT_TYPE_BRAND
            ? $discount->brands->pluck('brand_id')
            : $this->input->brands->keys();

        # За исключением брендов
        $exceptBrandIds = $this->getExceptBrandsForDiscount($discount);
        $brandIds = $brandIds->diff($exceptBrandIds);
        # За исключением офферов
        $exceptOfferIds = $this->getExceptOffersForDiscount($discount);
        # Отбираем нужные офферы
        $offerIds = $this->filterForBrand($brandIds, $exceptOfferIds, $discount->merchant_id);

        return $this->applyDiscountToOffers($discount, $offerIds);
    }

    /**
     * Для определенных категорий или для всех
     * @param Discount $discount
     * @return float|null
     */
    public function applyDiscountTypeCategory(Discount $discount): ?float
    {
        /** @var Collection $categoryIds */
        $categoryIds = $discount->type == Discount::DISCOUNT_TYPE_CATEGORY
            ? $discount->categories->pluck('category_id')
            : $this->input->categories->keys();

        # За исключением категорий
        $exceptCategoryIds = $this->getExceptCategoriesForDiscount($discount);
        $categoryIds = $categoryIds->diff($exceptCategoryIds);
        # За исключением брендов
        $exceptBrandIds = $this->getExceptBrandsForDiscount($discount);
        # За исключением офферов
        $exceptOfferIds = $this->getExceptOffersForDiscount($discount);
        # Отбираем нужные офферы
        $offerIds = $this->filterForCategory(
            $categoryIds,
            $exceptBrandIds,
            $exceptOfferIds,
            $discount->merchant_id
        );

        return $this->applyDiscountToOffers($discount, $offerIds);
    }

    /**
     * На доставку
     * @param Discount $discount
     * @return bool|float|null
     */
    public function applyDiscountTypeDelivery(Discount $discount): bool|null|float
    {
        // Если используется бесплатная доставка (например, по промокоду), то не использовать скидку
        if ($this->input->freeDelivery) {
            return false;
        }

        /*
         * Считаются только возможные скидки.
         * Берем все доставки, для которых необходимо посчитать только возможную скидку,
         * по очереди применяем скидки (откатывая предыдущие изменения, т.к. нельзя выбрать сразу две доставки),
         */
        $currentDeliveryId = $this->input->deliveries['current']['id'] ?? null;
        $change = false;

        $this
            ->input
            ->deliveries['items']
            ->transform(function ($delivery) use ($discount, $currentDeliveryId, &$change) {
                $deliveryApplier = new DeliveryApplier(
                    $this->input,
                    $this->basketItemsByDiscounts,
                    $this->appliedDiscounts
                );
                $deliveryApplier->setCurrentDelivery($delivery);
                $changedPrice = $deliveryApplier->apply($discount);
                $currentDelivery = $deliveryApplier->getModifiedCurrentDelivery();

                if ($currentDelivery['id'] === $currentDeliveryId) {
                    $change = $changedPrice;
                    $this->input->deliveries['current'] = $currentDelivery;
                }
                return $currentDelivery;
            });

        return $change;
    }

    /**
     * На сумму корзины
     * @param Discount $discount
     * @return float|null
     */
    public function applyDiscountTypeCartTotal(Discount $discount): ?float
    {
        $basketApplier = new BasketApplier(
            $this->input,
            $this->basketItemsByDiscounts,
            $this->appliedDiscounts
        );
        $change = $basketApplier->apply($discount);
        $this->basketItemsByDiscounts = $basketApplier->getModifiedBasketItemsByDiscounts();
        $this->input->basketItems = $basketApplier->getModifiedInputBasketItems();

        return $change;
    }

    /**
     * На мастер-класс
     * @param Discount $discount
     * @return float|null
     */
    public function applyDiscountTypePublicEvent(Discount $discount): ?float
    {
        $ticketTypeIds = $discount->publicEvents
            ->pluck('ticket_type_id')
            ->toArray();

        $offerIds = $this->input->basketItems
            ->whereIn('ticket_type_id', $ticketTypeIds)
            ->pluck('offer_id');

        return $this->applyDiscountToOffers($discount, $offerIds);
    }

    /**
     * На все мастер-классы
     * @param Discount $discount
     * @return float|null
     */
    public function applyDiscountTypeAnyPublicEvent(Discount $discount): ?float
    {
        $offerIds = $this->input->basketItems
            ->whereStrict('product_id', null)
            ->pluck('offer_id');

        return $this->applyDiscountToOffers($discount, $offerIds);
    }

    /**
     * Применяет скидку к офферам
     * @param Discount $discount
     * @param Collection $offerIds
     * @return float|null
     */
    protected function applyDiscountToOffers(Discount $discount, Collection $offerIds): ?float
    {
        $offerApplier = new OfferApplier(
            $this->input,
            $this->basketItemsByDiscounts,
            $this->appliedDiscounts
        );
        $offerApplier->setOfferIds($offerIds);
        $change = $offerApplier->apply($discount);

        $this->basketItemsByDiscounts = $offerApplier->getModifiedBasketItemsByDiscounts();
        $this->input->basketItems = $offerApplier->getModifiedInputBasketItems();

        return $change;
    }

    /**
     * Добавить скидку к примененным
     * @param Discount $discount
     * @param float|false $change
     * @return void
     */
    protected function addDiscountToApplied(Discount $discount, float|false $change): void
    {
        $this->appliedDiscounts->put($discount->id, [
            'discountId' => $discount->id,
            'change' => $change,
            'conditions' => $discount->conditions->pluck('type') ?? collect(),
            'summarizable_with_all' => $discount->summarizable_with_all,
            'max_priority' => $discount->max_priority,
        ]);
    }

    /**
     * Применить скидки на доставку (применяется первая, которая сработала)
     * @return void
     */
    protected function applyDeliveryDiscounts(): void
    {
        $deliveryDiscounts = $this->discounts
            ->where('type', Discount::DISCOUNT_TYPE_DELIVERY);

        $percentDiscounts = $deliveryDiscounts
            ->where('value_type', Discount::DISCOUNT_VALUE_TYPE_PERCENT)
            ->sortByDesc('value');

        $rubDiscounts = $deliveryDiscounts
            ->where('value_type', Discount::DISCOUNT_VALUE_TYPE_RUB)
            ->sortByDesc('value');

        /* сначала пробуем применять самые выгодные скидки */
        foreach ($percentDiscounts as $discount) {
            if ($this->applyDiscount($discount)) {
                return;
            }
        }

        foreach ($rubDiscounts as $discount) {
            if ($this->applyDiscount($discount)) {
                return;
            }
        }
    }

    /**
     * Считает размер скидки
     * @param Discount $discount
     * @param Collection $offerIds
     * @return float|null
     */
    protected function calculateDiscountToOffersChange(Discount $discount, Collection $offerIds): ?float
    {
        $offerApplier = new OfferApplier(
            $this->input,
            $this->basketItemsByDiscounts,
            $this->appliedDiscounts
        );
        $offerApplier->setOfferIds($offerIds);
        return $offerApplier->apply($discount, true) ?? 0;
    }


    /**
     * Генерирует временные DiscountCondition для скидок,
     * которые суммируются со всеми (summarizable_with_all)
     */
    protected function compileSynegry(): self
    {
        [
            $summarizableWithAll,
            $otherDiscounts
        ] = $this->possibleDiscounts->partition('summarizable_with_all', true);

        if ($summarizableWithAll->count() == 0) {
            return $this;
        }

        /** @var Discount $summarizableDiscount */
        foreach ($summarizableWithAll as $summarizableDiscount) {
            /** @var Discount $discount */
            foreach ($otherDiscounts as $discount) {

                /** @var DiscountConditionModel $condition */
                $condition = $discount->conditions->firstWhere('type', DiscountConditionModel::DISCOUNT_SYNERGY);

                if (!$condition) {
                    $condition = new DiscountConditionModel();
                    $condition->type = DiscountConditionModel::DISCOUNT_SYNERGY;
                    $discount->conditions->add($condition);
                }

                $condition->condition = array_merge_recursive($condition->condition ?? [], [
                    DiscountCondition::FIELD_SYNERGY => [$summarizableDiscount->id],
                ]);
            }
        }

        return $this;
    }

    /**
     * Условия скидок, которые не должны проверяться
     * @return array
     */
    protected function getExceptingConditionTypes(): array
    {
        return [
            DiscountConditionModel::DELIVERY_METHOD,
        ];
    }

    /**
     * @return bool
     */
    protected function needCalculate(): bool
    {
        return $this->input->payment['isNeedCalculate'];
    }

    /**
     * @return void
     */
    protected function fetchDiscounts(): void
    {
        $discountFetcher = new DiscountFetcher($this->input);
        $this->discounts = $discountFetcher->getDiscounts();
    }

    /**
     * @return void
     */
    private function getDiscountOutput(): void
    {
        $discountOutput = new DiscountOutput(
            $this->input,
            $this->discounts,
            $this->basketItemsByDiscounts,
            $this->appliedDiscounts
        );
        $this->input->basketItems = $discountOutput->getBasketItems();
        $this->basketItemsByDiscounts = $discountOutput->getModifiedBasketItemsByDiscounts();
        $this->appliedDiscounts = $discountOutput->getModifiedAppliedDiscounts();
        $this->output->appliedDiscounts = $discountOutput->getOutputFormat();
    }

    /**
     * Полностью откатывает все примененные скидки
     * @return $this
     */
    public function forceRollback(): self
    {
        return $this->rollback();
    }

    /**
     * Делает товар бесплатным, если была применена только
     * одна скидка на 100% или равная сумме товара в рублях
     * @return $this
     */
    protected function processFreeProducts(): self
    {
        foreach ($this->input->basketItems as $basketItem) {
            if ($this->basketItemsByDiscounts->has($basketItem['id'])) {
                [$freeProductDiscounts, $otherDiscounts] = $this->basketItemsByDiscounts->get($basketItem['id'])
                    ->partition(fn ($discount) =>
                        ((int) $discount['value_type'] === Discount::DISCOUNT_VALUE_TYPE_PERCENT && (int) $discount['value'] === 100) ||
                        ((int) $discount['value_type'] === Discount::DISCOUNT_VALUE_TYPE_RUB && (float) $discount['value'] === (float) $basketItem['cost'])
                    );

                // Если применена 100% скидка на товар и нет других скидок, то делаем этот товар бесплатным
                if ($freeProductDiscounts->count() > 0 && (!$otherDiscounts->count() || $otherDiscounts->count() === 0)) {
                    $basketItem['price'] = 0;
                }
            }
        }

        return $this;
    }

    /**
     * @param Discount $discount
     * @return Collection
     */
    protected function getExceptOffersForDiscount(Discount $discount): Collection
    {
        return $discount
            ->offers
            ->where('except', true)
            ->pluck('offer_id');
    }

    /**
     * @param Discount $discount
     * @return Collection
     */
    protected function getExceptBrandsForDiscount(Discount $discount): Collection
    {
        return $discount
            ->brands
            ->where('except', true)
            ->pluck('brand_id');
    }

    /**
     * @param Discount $discount
     * @return Collection
     */
    protected function getExceptCategoriesForDiscount(Discount $discount): Collection
    {
        return $discount
            ->categories
            ->where('except', true)
            ->pluck('category_id');
    }

    /**
     * Совместимы ли скидки (даже если они не пересекаются)
     * @param Discount $discount
     * @return bool
     */
    protected function isCompatibleDiscount(Discount $discount): bool
    {
        return !$this->appliedDiscounts->has($discount->id);
    }

    /**
     * Откатывает все примененные скидки
     * @return $this
     */
    protected function rollback(): self
    {
        $this->appliedDiscounts = collect();
        $this->basketItemsByDiscounts = collect();
        $this->output->appliedDiscounts = collect();

        $basketItems = collect();
        foreach ($this->input->basketItems as $basketItem) {
            $basketItem['price'] = $basketItem['cost'] ?? $basketItem['price'];
            unset($basketItem['discount']);
            unset($basketItem['cost']);
            $basketItems->put($basketItem['id'], $basketItem);
        }
        $this->input->basketItems = $basketItems;

        $deliveries = collect();
        foreach ($this->input->deliveries['items'] as $delivery) {
            $delivery['price'] = $delivery['cost'] ?? $delivery['price'];
            unset($delivery['discount']);
            unset($delivery['cost']);
            $deliveries->put($delivery['id'], $delivery);
        }
        $this->input->deliveries['items'] = $deliveries;

        return $this;
    }

    /**
     * @param Collection $discounts
     * @return Collection
     */
    protected function sortDiscountsByProfit(Collection $discounts): Collection
    {
        return $discounts->sortByDesc(function (Discount $discount) {
            $change = $this->calcDiscountChange($discount);
            return $change;
        });
    }

    /**
     * Посчитать размер скидки
     * @param Discount $discount
     * @return float|null
     */
    private function calcDiscountChange(Discount $discount): ?float
    {
        //TODO: refactor to match{}
        switch ($discount->type) {
            case Discount::DISCOUNT_TYPE_OFFER:
                $offerIds = $discount->offers->pluck('offer_id');
                $offerApplier = new OfferApplier($this->input, $this->basketItemsByDiscounts, $this->appliedDiscounts);
                $offerApplier->setOfferIds($offerIds);
                return $offerApplier->apply($discount, true);
            case Discount::DISCOUNT_TYPE_ANY_OFFER:
                $exceptOfferIds = $this->getExceptOffersForDiscount($discount);
                $offerIds = $this->input->basketItems
                    ->where('product_id', '!=', null)
                    ->whereNotIn('offer_id', $exceptOfferIds)
                    ->pluck('offer_id');

                $offerApplier = new OfferApplier($this->input, $this->basketItemsByDiscounts, $this->appliedDiscounts);
                $offerApplier->setOfferIds($offerIds);
                return $offerApplier->apply($discount, true);
            case Discount::DISCOUNT_TYPE_BUNDLE_OFFER:
            case Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS:
            case Discount::DISCOUNT_TYPE_ANY_BUNDLE:
                $bundleItems = $discount->bundleItems;
                if (in_array($discount->type, [Discount::DISCOUNT_TYPE_BUNDLE_OFFER, Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS])) {
                    $offerIds = $bundleItems->pluck('item_id');
                } else {
                    $exceptBundleIds = $discount->bundles->pluck('bundle_id');
                    $offerIds = $this->input->basketItems->filter(function ($basketItem) use ($exceptBundleIds) {
                        return $basketItem['bundle_id'] > 0 && !$exceptBundleIds->contains($basketItem['bundle_id']);
                    })
                        ->pluck('offer_id');
                }

                if (in_array($discount->type, [Discount::DISCOUNT_TYPE_BUNDLE_OFFER, Discount::DISCOUNT_TYPE_ANY_BUNDLE])) {
                    $offerApplier = new OfferApplier($this->input, $this->basketItemsByDiscounts, $this->appliedDiscounts);
                    $offerApplier->setOfferIds($offerIds);
                    return $offerApplier->apply($discount, true);
                }
                return 0;
            case Discount::DISCOUNT_TYPE_BRAND:
            case Discount::DISCOUNT_TYPE_ANY_BRAND:
                /** @var Collection $brandIds */
                $brandIds = $discount->type == Discount::DISCOUNT_TYPE_BRAND
                    ? $discount->brands->pluck('brand_id')
                    : $this->input->brands->keys();

                $exceptBrandIds = $this->getExceptBrandsForDiscount($discount);
                $brandIds = $brandIds->diff($exceptBrandIds);

                $exceptOfferIds = $this->getExceptOffersForDiscount($discount);

                $offerIds = $this->filterForBrand($brandIds, $exceptOfferIds, $discount->merchant_id);
                $offerApplier = new OfferApplier($this->input, $this->basketItemsByDiscounts, $this->appliedDiscounts);
                $offerApplier->setOfferIds($offerIds);
                return $offerApplier->apply($discount, true);
            case Discount::DISCOUNT_TYPE_CATEGORY:
            case Discount::DISCOUNT_TYPE_ANY_CATEGORY:
                /** @var Collection $categoryIds */
                $categoryIds = $discount->type == Discount::DISCOUNT_TYPE_CATEGORY
                    ? $discount->categories->pluck('category_id')
                    : $this->input->categories->keys();

                $exceptCategoryIds = $this->getExceptCategoriesForDiscount($discount);
                $categoryIds = $categoryIds->diff($exceptCategoryIds);

                $exceptBrandIds = $this->getExceptBrandsForDiscount($discount);

                $exceptOfferIds = $this->getExceptOffersForDiscount($discount);

                $offerIds = $this->filterForCategory(
                    $categoryIds,
                    $exceptBrandIds,
                    $exceptOfferIds,
                    $discount->merchant_id
                );
                $offerApplier = new OfferApplier($this->input, $this->basketItemsByDiscounts, $this->appliedDiscounts);
                $offerApplier->setOfferIds($offerIds);
                return $offerApplier->apply($discount, true);
            case Discount::DISCOUNT_TYPE_DELIVERY:
                if ($this->input->freeDelivery) {
                    return 0;
                }

                $currentDeliveryId = $this->input->deliveries['current']['id'] ?? null;
                $this->input->deliveries['items']->each(function ($delivery) use ($discount, $currentDeliveryId, &$change) {
                    $deliveryApplier = new DeliveryApplier($this->input, $this->basketItemsByDiscounts, $this->appliedDiscounts);
                    $deliveryApplier->setCurrentDelivery($delivery);
                    $changedPrice = $deliveryApplier->apply($discount, true);
                    $currentDelivery = $deliveryApplier->getModifiedCurrentDelivery();

                    if ($currentDelivery['id'] === $currentDeliveryId) {
                        $change = $changedPrice;
                    }
                });
                return $change ?? 0;
            case Discount::DISCOUNT_TYPE_CART_TOTAL:
                $basketApplier = new BasketApplier($this->input, $this->basketItemsByDiscounts, $this->appliedDiscounts);
                return $basketApplier->apply($discount, true);
            case Discount::DISCOUNT_TYPE_MASTERCLASS:
                $ticketTypeIds = $discount->publicEvents
                    ->pluck('ticket_type_id')
                    ->toArray();

                $offerIds = $this->input->basketItems
                    ->whereIn('ticket_type_id', $ticketTypeIds)
                    ->pluck('offer_id');

                $offerApplier = new OfferApplier($this->input, $this->basketItemsByDiscounts, $this->appliedDiscounts);
                $offerApplier->setOfferIds($offerIds);
                return $offerApplier->apply($discount, true);
            case Discount::DISCOUNT_TYPE_ANY_MASTERCLASS:
                $offerIds = $this->input->basketItems
                    ->whereStrict('product_id', null)
                    ->pluck('offer_id');

                $offerApplier = new OfferApplier($this->input, $this->basketItemsByDiscounts, $this->appliedDiscounts);
                $offerApplier->setOfferIds($offerIds);
                return $offerApplier->apply($discount, true);
            default:
                return 0;
        }
    }
}

