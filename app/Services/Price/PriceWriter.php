<?php

namespace App\Services\Price;

use App\Models\Price\Price;
use App\Models\Price\PriceByRole;
use App\Services\Price\Calculators\AbstractPriceCalculator;
use app\Services\Price\Calculators\GuestCustomerPriceCalculator;
use App\Services\Price\Calculators\PriceCalculatorInterface;
use App\Services\Price\Calculators\ProfPriceCalculator;
use App\Services\Price\Calculators\ReferralPriceCalculator;
use App\Services\Price\Calculators\RetailPriceCalculator;
use App\Services\Price\Calculators\SalonPriceCalculator;
use Illuminate\Database\Eloquent\Collection;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
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
            ->with('pricesByRoles')
            ->whereIn('offer_id', $offerIds)
            ->get()
            ->keyBy('offer_id');
    }

    private function syncPrice(int $offerId, float $newPrice, bool $nullable): Price
    {
        $price = $this->prices->get($offerId);

        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        $offersQuery = $offerService->newQuery()
            ->setFilter('id', $offerId)
            ->include(ProductDto::entity())
            ->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id')
            ->addFields(ProductDto::entity(), 'id', 'category_id', 'brand_id');

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

        $this->generatePricesByRoles($offer, $price);

        return $price;
    }

    /**
     * Подсчитываем и сохраняем стоимость товара для каждой роли
     */
    public function generatePricesByRoles(OfferDto $offer, Price $basePrice): void
    {
        $basePrice->loadMissing('pricesByRoles');

        $calculators = [
            ProfPriceCalculator::class,
            ReferralPriceCalculator::class,
            RetailPriceCalculator::class,
            GuestCustomerPriceCalculator::class,
            SalonPriceCalculator::class,
        ];

        /** @var AbstractPriceCalculator $calculator */
        foreach ($calculators as $calculatorClass) {
            $calculator = new $calculatorClass($offer, $basePrice);
            $priceByRoleFloat = $calculator->calculatePrice();

            $priceByRole = $basePrice->pricesByRoles->filter(fn($tmpPriceByRole) => $tmpPriceByRole->role == $calculator->getRole())->first();
            if (!$priceByRole) {
                $priceByRole = new PriceByRole();
                $priceByRole->role = $calculator->getRole();
                $priceByRole->price_id = $basePrice->id;
            }
            $priceByRole->price = $priceByRoleFloat;
            $priceByRole->percent_by_base_price = $basePrice->price > 0
                ? round(($priceByRoleFloat - $basePrice->price) / $basePrice->price * 100)
                : 0;
            $priceByRole->save();
        }
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
