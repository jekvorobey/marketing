<?php

namespace App\Services\Calculator\Catalog;

use App\Models\Price\Price;
use App\Services\Calculator\Bonus\BonusCatalogCalculator;
use App\Services\Calculator\Discount\DiscountCatalogCalculator;
use Illuminate\Support\Collection;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;
use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;

/**
 * Класс для расчета скидок (цен) для отображения в каталоге
 * Class CatalogPriceCalculator
 * @package App\Services\Discount
 */
class CatalogCalculator extends AbstractCalculator
{
    const MAX_CHUNK = 1000;

    /**
     * @var Collection
     */
    protected $offerIds;

    /**
     * DiscountPriceCalculator constructor.
     *
     * @param array|null $params
     *  [
     *  'offer_ids' => int[] – ID офферов
     *  'role_ids' => int[]|null, – Роли пользователя
     *  'segment_id' => int|null, – Сегмент пользователя
     *  ]
     *
     * @throws PimException
     */
    public function __construct(array $params = [])
    {
        $input  = new InputCalculator($params);
        $output = new OutputCalculator();
        parent::__construct($input, $output);

        $this->offerIds = isset($params['offer_ids'])
            ? collect($params['offer_ids'])->flip()
            : collect();

        $this->fetchOffers()
            ->fetchPrice()
            ->fetchProduct();
    }

    /**
     * @return array
     */
    public function calculate()
    {
        $calculators = [
            DiscountCatalogCalculator::class,
            BonusCatalogCalculator::class,
        ];

        foreach ($calculators as $calculatorName) {
            /** @var AbstractCalculator $calculator */
            $calculator = new $calculatorName($this->input, $this->output);
            $calculator->calculate();
        }

        return $this->getFormatOffers();
    }

    /**
     * @return array
     */
    public function getFormatOffers()
    {
        return $this->input->offers->map(function ($offer, $offerId) {
            return [
                'offer_id' => $offerId,
                'price' => $offer['price'],
                'cost' => $offer['cost'] ?? $offer['price'],
                'discounts' => $offer['discounts'] ?? null,
                'bonus' => $offer['bonus'] ?? 0,
            ];
        })->values()->toArray();
    }

    /**
     * @return $this
     * @throws PimException
     */
    protected function fetchOffers()
    {
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        $this->offerIds->chunk(self::MAX_CHUNK)->each(function ($offerIds) use ($offerService) {
            $offerQuery = $offerService->newQuery();
            $offerQuery->setFilter('id', $offerIds->keys()->toArray());
            $offerQuery->addFields(
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

                $this->input->offers->put($offer->id, collect([
                    'id' => $offer->id,
                    'product_id' => $offer->product_id,
                    'qty' => 1,
                    'price' => null,
                    'brand_id' => null,
                    'category_id' => null,
                    'merchant_id' => $offer->merchant_id,
                ]));
            }
        });

        return $this;
    }

    /**
     * @return $this
     */
    protected function fetchPrice()
    {
        $this->offerIds->chunk(self::MAX_CHUNK)->each(function ($offerIds) {
            $prices = Price::select(['offer_id', 'price'])
                ->whereIn('offer_id', $offerIds->keys())
                ->get()
                ->pluck('price', 'offer_id');

            foreach ($this->input->offers as $offer) {
                $offerId = $offer['id'];
                if ($prices->has($offerId)) {
                    $offer['price'] = $prices[$offerId];
                }
            }
        });

        return $this;
    }

    /**
     * @return $this
     * @throws PimException
     */
    protected function fetchProduct()
    {
        $productIds = $this->input->offers->pluck('product_id');
        $productIds->chunk(self::MAX_CHUNK)->each(function ($productIds) {
            /** @var ProductService $offerService */
            $productService = resolve(ProductService::class);
            $productQuery = $productService->newQuery();
            $productQuery->setFilter('id', $productIds->toArray());
            $productQuery->addFields(
                ProductDto::entity(),
                'id',
                'category_id',
                'brand_id'
            );

            $products = $productService->products($productQuery)->keyBy('id');
            foreach ($this->input->offers as $offer) {
                $productId = $offer['product_id'];
                if ($products->has($productId)) {
                    $offer['brand_id']    = $products[$productId]['brand_id'];
                    $offer['category_id'] = $products[$productId]['category_id'];
                }
            }
        });

        return $this;
    }
}
