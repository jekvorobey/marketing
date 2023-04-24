<?php

namespace App\Services\Price;

use App\Models\Price\Price;
use Illuminate\Database\Eloquent\Collection;
use MerchantManagement\Dto\MerchantPricesDto;
use MerchantManagement\Services\MerchantService\Dto\GetMerchantPricesDto;
use MerchantManagement\Services\MerchantService\MerchantService;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;
use Pim\Services\SearchService\SearchService;

class PriceWriter
{
    private Collection $prices;

    /**
     * @param array|float[] $newPrices - массив новых цен вида [offerId => price]
     * @param bool $nullable - сохранять ли нулевую цену
     * @throws PimException
     */
    public function setPrices(array $newPrices, bool $nullable = false): void
    {
        $this->loadPrices($newPrices);

        $updatedOfferIds = [];

        foreach ($newPrices as $offerId => $newPrice) {
            try {
                $price = $this->syncPrice($offerId, $newPrice, $nullable);
            } catch (\Throwable $e) {
                report($e);
                continue;
            }

            if (!$price->exists || $price->wasRecentlyCreated || $price->wasChanged()) {
                $updatedOfferIds[] = $price->offer_id;
            }
        }

        if ($updatedOfferIds) {
            rescue(fn() => $this->markOffersForIndex($updatedOfferIds));
        }
    }

    public function pricesByOffers(array $offerIds): ?Collection
    {
        return Price::query()
            ->select('offer_id', 'price')
            ->whereIn('offer_id', $offerIds)
            ->get()
            ->keyBy('offer_id');
    }

    private function loadPrices(array $newPrices): void
    {
        $offerIds = array_keys($newPrices);

        $this->prices = Price::query()
            ->whereIn('offer_id', $offerIds)
            ->get()
            ->keyBy('offer_id');
    }

    private function syncPrice(int $offerId, float $newPrice, bool $nullable): Price
    {
        $price = $this->prices->get($offerId);

        try {
            $merchantOfferPrices = $this->getMerchantOfferPrices($offerId);
            /** @var MerchantPricesDto|null $baseOfferPrice */
            $baseOfferPrice = $merchantOfferPrices['sku'] ?? $merchantOfferPrices['category'] ?? $merchantOfferPrices['brand'] ?? $merchantOfferPrices['personal'] ?? null;
            $merchantId = $merchantOfferPrices['merchant_id'] ?? null;
        } catch (PimException) {
            $baseOfferPrice = null;
        }

        $priceBase = $newPrice;
        $priceRetail = $newPrice;
        if ($baseOfferPrice instanceof MerchantPricesDto) {
            if ($baseOfferPrice->valueProf) {
                $newPrice = round($newPrice + $newPrice * $baseOfferPrice->valueProf / 100, 2);
            }
            if ($baseOfferPrice->valueRetail) {
                $priceRetail = round($newPrice + $newPrice * $baseOfferPrice->valueRetail / 100, 2);
            }
        }

        if (!$nullable && $price && !$newPrice) {
            $price->delete();
        } else {
            if (!$price) {
                $price = new Price();
            }
            $price->merchant_id = $merchantId ?? null;
            $price->offer_id = $offerId;
            $price->price = $newPrice;
            $price->price_base = $priceBase;
            $price->price_retail = $priceRetail;

            $price->save();
        }

        return $price;
    }

    /**
     * @throws PimException
     */
    private function markOffersForIndex(array $offerIds): void
    {
        if (!$offerIds) {
            return;
        }

        /** @var SearchService $searchService */
        $searchService = resolve(SearchService::class);
        $searchService->markProductsForIndexByOfferIds($offerIds);
    }

    /**
     * Получить ценообразование конкретного оффера
     * @throws PimException
     */
    protected function getMerchantOfferPrices(int $offerId): array
    {
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        /** @var ProductService $productService */
        $productService = resolve(ProductService::class);
        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);

        $offersQuery = $offerService->newQuery()
            ->setFilter('id', $offerId)
            ->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id');
        $offers = $offerService->offers($offersQuery);
        /** @var OfferDto $offer */
        $offer = $offers->firstOrFail();

        $productsQuery = $productService->newQuery()
            ->setFilter('id', $offer->product_id)
            ->addFields(ProductDto::entity(), 'id', 'category_id', 'brand_id');
        $products = $productService->products($productsQuery);
        /** @var ProductDto $product */
        $product = $products->firstOrFail();

        $merchantPrices = $merchantService->merchantPrices(
            (new GetMerchantPricesDto())
                ->addType(MerchantPricesDto::TYPE_MERCHANT)
                ->addType(MerchantPricesDto::TYPE_BRAND)
                ->addType(MerchantPricesDto::TYPE_CATEGORY)
                ->addType(MerchantPricesDto::TYPE_SKU)
                ->setMerchantId($offer->merchant_id)
        );

        $merchantOfferPrices = [
            'offer_id' => $offer->id,
            'merchant_id' => $offer->merchant_id,
            'product_id' => $offer->product_id,
            'brand_id' => $product?->brand_id,
            'category_id' => $product?->category_id,
            'personal' => null,
            'brand' => null,
            'category' => null,
            'sku' => null,
        ];

        foreach ($merchantPrices as $merchantPrice) {
            switch ($merchantPrice->type) {
                case MerchantPricesDto::TYPE_MERCHANT:
                    $merchantOfferPrices['personal'] = $merchantPrice;
                    break;
                case MerchantPricesDto::TYPE_BRAND:
                    $merchantOfferPrices['brand'] = $merchantPrice;
                    break;
                case MerchantPricesDto::TYPE_CATEGORY:
                    $merchantOfferPrices['category'] = $merchantPrice;
                    break;
                case MerchantPricesDto::TYPE_SKU:
                    $merchantOfferPrices['sku'] = $merchantPrice;
                    break;
            }
        }

        return $merchantOfferPrices;
    }
}
