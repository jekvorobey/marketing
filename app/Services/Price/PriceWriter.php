<?php

namespace App\Services\Price;

use App\Models\Price\Price;
use App\Models\Price\PriceByRole;
use App\Services\Price\Calculators\AbstractPriceCalculator;
use App\Services\Price\Calculators\PriceCalculatorInterface;
use App\Services\Price\Calculators\ProfPriceCalculator;
use App\Services\Price\Calculators\RetailPriceCalculator;
use Illuminate\Database\Eloquent\Collection;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\SearchService\SearchService;

class PriceWriter
{
    private Collection $prices;
    private Collection $pricesByRoles;
    private ?array $merchantPrices = [];

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
            ->select('offer_id', 'price', 'updated_at')
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

        $this->pricesByRoles = PriceByRole::query()
            ->whereIn('offer_id', $offerIds)
            ->get()
            ->groupBy('offer_id')
            ->transform(fn($tmpPriceByRole) => $tmpPriceByRole->keyBy('role'));
    }

    private function syncPrice(int $offerId, float $newPrice, bool $nullable): Price
    {
        $price = $this->prices->get($offerId);

        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        $offersQuery = $offerService->newQuery()
            ->setFilter('id', $offerId)
            ->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id');
        /** @var OfferDto $offer */
        $offer = $offerService->offers($offersQuery)->firstOrFail();

        if (!$nullable && $price && !$newPrice) {
            $price->delete();
        } else {
            if (!$price) {
                $price = new Price();
            }
            $price->offer_id = $offerId;
            $price->price = $newPrice;
            $price->merchant_id = $offer->merchant_id ?? null;

            $price->save();
        }

        // Подсчитываем стоимость товара для каждой роли
        $calculators = [
            new ProfPriceCalculator($offer),
            new RetailPriceCalculator($offer),
        ];

        /** @var AbstractPriceCalculator $calculator */
        foreach ($calculators as $calculator) {
            $priceByRoleFloat = $calculator->calculatePrice($newPrice);

            $priceByRole = $this->pricesByRoles[$offer->id][$calculator->getRole()] ?? null;
            if (!$priceByRole) {
                $priceByRole = new PriceByRole();
                $priceByRole->role = $calculator->getRole();
                $priceByRole->offer_id = $offer->id;
            }
            $priceByRole->merchant_id = $offer->merchant_id ?? null;
            $priceByRole->price = $priceByRoleFloat;
            $priceByRole->percent_by_base_price = round(($priceByRoleFloat - $newPrice) / $newPrice * 100);
            $priceByRole->save();
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
