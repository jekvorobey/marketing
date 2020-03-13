<?php

namespace App\Services\Discount;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountOffer;
use Pim\Core\PimException;
use App\Models\Price\Price;
use Illuminate\Support\Collection;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;

/**
 * Класс для расчета скидок (цен) для отображения в каталоге
 * Class DiscountPriceCalculator
 * @package App\Services\Discount
 */
class DiscountCatalogPrice extends DiscountCalculator
{
    /**
     * @var int|null
     */
    protected $roleId;

    /**
     * @var Collection
     */
    protected $offerIds;

    /**
     * @var int|null
     */
    protected $segmentId;

    /**
     * @var int|null
     */
    protected $userId;

    /**
     * DiscountPriceCalculator constructor.
     * @param array|null $params
     * [
     *  'offer_ids' => array|null, – ID офферов
     *  'role_id' => int|null, – Роль пользователя
     *  'segment_id' => int|null, – Сегмент пользователя
     *  'user_id' => int|null – ID пользователя (используется, если не указаны role_id и segment_id)
     * ]
     */
    public function __construct(array $params = [])
    {
        $this->offerIds = isset($params['offer_ids'])
            ? collect($params['offer_ids'])->flip()
            : collect();

        $this->roleId = $params['role_id'] ?? null;
        $this->segmentId = $params['segment_id'] ?? null;
        $this->userId = $params['user_id'] ?? null;

        // todo Учитывать роль и сегмент пользователя
        $params = (new DiscountCalculatorBuilder())->getParams();
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
            ->apply();

        return $this->filter['offers']->map(function ($offer, $offerId) {
            return [
                'offer_id' => $offerId,
                'price' => $offer['price'],
                'cost' => $offer['cost'] ?? $offer['price'],
                'discounts' => $this->offersByDiscounts[$offerId] ?? null
            ];
        })->values();
    }

    /**
     * Загружает все необходимые данные
     * @return $this|DiscountCalculator
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
            'product_id'
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
        $this->discounts = Discount::select(['id', 'type', 'value', 'value_type', 'promo_code_only'])
            ->showInCatalog()
            ->orderBy('type')
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

    /**
     * @param Discount $discount
     * @return bool
     */
    protected function isCompatible(Discount $discount)
    {
        return true;
    }

    /**
     * Можно ли применить скидку к офферу
     * @param $discount
     * @param $offerId
     * @return bool
     */
    protected function applicableToOffer($discount, $offerId)
    {
        if ($this->appliedDiscounts->isEmpty() || !$this->offersByDiscounts->has($offerId)) {
            return true;
        }

        if (!$this->relations['conditions']->has($discount->id)) {
            return false;
        }

        $discountIdsForOffer = $this->offersByDiscounts[$offerId]->pluck('id');
        /** @var DiscountCondition $condition */
        foreach ($this->relations['conditions'][$discount->id] as $condition) {
            if ($condition->type === DiscountCondition::DISCOUNT_SYNERGY) {
                $synergyDiscountIds = $condition->getSynergy();
                return $discountIdsForOffer->intersect($synergyDiscountIds)->count() === $discountIdsForOffer->count();
            }
        }

        return false;
    }
}
