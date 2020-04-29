<?php

namespace App\Services\Price;

use App\Models\Bonus\Bonus;
use App\Models\Discount\Discount;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountOffer;
use App\Models\Price\Price;
use Illuminate\Support\Collection;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;

/**
 * Класс для расчета скидок (цен) для отображения в каталоге
 * Class CatalogPriceCalculator
 * @package App\Services\Discount
 */
class CatalogPriceCalculator extends CheckoutPriceCalculator
{
    /**
     * @var Collection
     */
    protected $offerIds;

    /**
     * DiscountPriceCalculator constructor.
     * @param array|null $params
     * [
     *  'offer_id' => array|int|null, – ID офферов
     *  'role_ids' => int[]|null, – Роли пользователя
     *  'segment_id' => int|null, – Сегмент пользователя
     * ]
     */
    public function __construct(array $params = [])
    {
        $this->offerIds = isset($params['offer_ids'])
            ? collect($params['offer_ids'])->flip()
            : collect();

        $params = (new CheckoutPriceCalculatorBuilder())
            ->customer([
                'roles' => $params['role_ids'] ?? null,
                'segment' => $params['segment_id'] ?? null,
            ])
            ->getParams();

        parent::__construct($params);
    }

    /**
     * @return array
     */
    public function calculate()
    {
        $this->getActiveDiscounts()
            ->fetchData()
            ->filter()
            ->sort()
            ->apply()
            ->getActiveBonuses()
            ->applyBonuses();

        return $this->getFormatOffers();
    }

    /**
     * @return array
     */
    public function getFormatOffers()
    {
        return $this->filter['offers']->map(function ($offer, $offerId) {
            $bonuses = $this->offersByBonuses[$offerId] ?? collect();
            return [
                'offer_id' => $offerId,
                'price' => $offer['price'],
                'cost' => $offer['cost'] ?? $offer['price'],
                'discounts' => $this->offersByDiscounts->has($offerId)
                    ? $this->offersByDiscounts[$offerId]->values()->toArray()
                    : null,
                'bonus' => $bonuses->reduce(function ($carry, $bonus) use ($offer) {
                    return $carry + $bonus['bonus'] * ($offer['qty'] ?? 1);
                }) ?? 0,
            ];
        })->values()->toArray();
    }

    /**
     * Загружает все необходимые данные
     * @return $this|CheckoutPriceCalculator
     * @throws PimException
     */
    protected function loadData()
    {
        $this->fetchCategories()
            ->fetchOffers()
            ->fetchPrice()
            ->fetchProduct();

        $this->filter['brands'] = $this->filter['offers']->pluck('brand_id', 'brand_id')
            ->unique()
            ->filter(function ($brandId) {
                return $brandId > 0;
            });


        $this->filter['categories'] = $this->filter['offers']->pluck('category_id', 'category_id')
            ->unique()
            ->filter(function ($brandId) {
                return $brandId > 0;
            });

        return $this;
    }

    /**
     * @return $this
     * @throws PimException
     */
    protected function fetchOffers()
    {
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        $offerQuery = $offerService->newQuery()->addFields(
            OfferDto::entity(),
            'id',
            'product_id',
            'merchant_id'
        );
        $offers = $offerService->offers($offerQuery);
        /** @var OfferDto $offer */
        foreach ($offers as $offer) {
            if ($this->offerIds->isNotEmpty() && !$this->offerIds->has($offer->id)) {
                continue;
            }

            $this->filter['offers']->put($offer->id, collect([
                'id' => $offer->id,
                'product_id' => $offer->product_id,
                'qty' => 1,
                'price' => null,
                'brand_id' => null,
                'category_id' => null,
                'merchant_id' => $offer->merchant_id,
            ]));
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function fetchPrice()
    {
        $offers = collect();
        $prices = Price::select(['offer_id', 'price'])->get()->pluck('price', 'offer_id');
        foreach ($this->filter['offers'] as $offer) {
            $offerId = $offer['id'];
            if ($prices->has($offerId)) {
                $offer['price'] = $prices[$offerId];
                $offers->put($offerId, $offer);
            }
        }

        $this->filter['offers'] = $offers;
        return $this;
    }

    /**
     * @return $this
     */
    protected function fetchProduct()
    {
        /** @var ProductService $offerService */
        $productService = resolve(ProductService::class);
        $productQuery = $productService->newQuery()->addFields(
            ProductDto::entity(),
            'id',
            'category_id',
            'brand_id'
        );

        $offers = collect();
        $products = $productService->products($productQuery)->keyBy('id');
        foreach ($this->filter['offers'] as $offer) {
            $productId = $offer['product_id'];
            if ($products->has($productId)) {
                $offer['brand_id'] = $products[$productId]['brand_id'];
                $offer['category_id'] = $products[$productId]['category_id'];
                $offers->put($offer['id'], $offer);
            }
        }

        $this->filter['offers'] = $offers;
        return $this;
    }

    /**
     * Получаем все возможные скидки и офферы из DiscountOffer
     * @return $this
     */
    protected function fetchDiscountOffers()
    {
        $this->relations['offers'] = DiscountOffer::select(['discount_id', 'offer_id', 'except'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->get()
            ->filter(function ($discountOffer) {
                return $this->filter['offers']->has($discountOffer['offer_id']);
            })
            ->groupBy('discount_id');
        return $this;
    }

    /**
     * Получаем все возможные скидки и бренды из DiscountBrand
     * @return $this
     */
    protected function fetchDiscountBrands()
    {
        $this->relations['brands'] = DiscountBrand::select(['discount_id', 'brand_id', 'except'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->get()
            ->filter(function ($discountBrand) {
                return $this->filter['brands']->has($discountBrand['brand_id']);
            })
            ->groupBy('discount_id');
        return $this;
    }

    /**
     * Получаем все возможные скидки и условия из DiscountCondition
     * @param Collection $discountIds
     * @return $this
     */
    protected function fetchDiscountConditions(Collection $discountIds)
    {
        $this->relations['conditions'] = DiscountCondition::select(['discount_id', 'type', 'condition'])
            ->whereIn('discount_id', $discountIds)
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Проверяет доступность применения скидки на все соответствующие условия
     *
     * @param Collection $conditions
     * @return bool
     * @todo
     */
    protected function checkConditions(Collection $conditions): bool
    {
        /** @var DiscountCondition $condition */
        foreach ($conditions as $condition) {
            switch ($condition->type) {
                /**
                 * Оставляем только скидки, у которых отсутсвуют доп. условия (считаются в корзине или чекауте).
                 */
                case DiscountCondition::DISCOUNT_SYNERGY:
                    continue(2);
                default:
                    return false;
            }
        }

        return true;
    }

    /**
     * Получить все активные скидки, которые могут быть показаны (рассчитаны) в каталоге
     *
     * @return $this
     */
    protected function getActiveDiscounts()
    {
        $this->discounts = Discount::select(['id', 'type', 'value', 'value_type', 'promo_code_only', 'merchant_id'])
            ->showInCatalog()
            ->orderBy('type')
            ->get();

        return $this;
    }

    /**
     * @return $this
     */
    protected function getActiveBonuses()
    {
        $this->bonuses = Bonus::query()
            ->where('type', '!=', Bonus::TYPE_CART_TOTAL)
            ->active()
            ->get();

        return $this;
    }

    /**
     * Можно ли применить данную скидку (независимо от других скидок)
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkDiscount(Discount $discount): bool
    {
        return $this->checkType($discount)
            && $this->checkCustomerRole($discount)
            && $this->checkSegment($discount);
    }
}
