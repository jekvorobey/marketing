<?php

namespace App\Services\Price;

use App\Models\Price\Price;
use Illuminate\Database\Eloquent\Collection;
use Pim\Core\PimException;
use Pim\Services\SearchService\SearchService;

class PriceWriter
{
    private Collection $prices;

    /**
     * @param array|float[] $newPrices - массив новых цен вида [offerId => price]
     * @param bool $nullable - сохранять ли нулевую цену
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

        if (!$nullable && $price && !$newPrice) {
            $price->delete();
        } else {
            if (!$price) {
                $price = new Price();
            }
            $price->offer_id = $offerId;
            $price->price = $newPrice;
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
}
