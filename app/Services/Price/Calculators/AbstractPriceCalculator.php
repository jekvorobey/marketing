<?php

namespace App\Services\Price\Calculators;

use App\Services\Price\Checkers\BrandChecker;
use App\Services\Price\Checkers\CategoryChecker;
use App\Services\Price\Checkers\MerchantChecker;
use App\Services\Price\Checkers\ProductChecker;
use Illuminate\Support\Collection;
use MerchantManagement\Dto\MerchantPricesDto;
use MerchantManagement\Services\MerchantService\Dto\GetMerchantPricesDto;
use MerchantManagement\Services\MerchantService\MerchantService;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\ProductService\ProductService;

abstract class AbstractPriceCalculator
{
    protected OfferDto $offer;
    protected MerchantService $merchantService;
    protected ProductService $productService;
    protected static array $merchantsPriceSettingsCache;

    public function __construct(OfferDto $offer)
    {
        $this->offer = $offer;
        $this->merchantService = resolve(MerchantService::class);
        $this->productService = resolve(ProductService::class);
    }

    /**
     * Id роли для которой подсчитывается цена
     * @return int
     */
    abstract public function getRole(): int;

    abstract public function calculatePrice(float $basePrice): float;

    /**
     * Все настройки ценообразования мерчанта
     */
    protected function getAllMerchantPriceSettings(): Collection
    {
        if (!isset(static::$merchantsPriceSettingsCache[$this->offer->merchant_id])) {
            $merchantPrices = $this->merchantService->merchantPrices(
                (new GetMerchantPricesDto())
                    ->addType(MerchantPricesDto::TYPE_MERCHANT)
                    ->addType(MerchantPricesDto::TYPE_BRAND)
                    ->addType(MerchantPricesDto::TYPE_CATEGORY)
                    ->addType(MerchantPricesDto::TYPE_SKU)
                    ->setMerchantId($this->offer->merchant_id)
            );
            static::$merchantsPriceSettingsCache[$this->offer->merchant_id] = $merchantPrices->keyBy('type');
        }

        return static::$merchantsPriceSettingsCache[$this->offer->merchant_id] ?? collect();
    }

    private function getProductByOffer(OfferDto $offer): ProductDto
    {
        $productsQuery = $this->productService->newQuery()
            ->setFilter('id', $this->offer->product_id)
            ->addFields(ProductDto::entity(), 'id', 'category_id', 'brand_id');

        return $this->productService->products($productsQuery)->firstOrFail();
    }

    /**
     * Находит настройки ценообразования для текущего оффера
     */
    protected function getRelevantMerchantPriceSettings(): ?MerchantPricesDto
    {
        $allMerchantPriceSettings = $this->getAllMerchantPriceSettings();
        if ($allMerchantPriceSettings->isEmpty()) {
            return null;
        }

        $product = $this->getProductByOffer($this->offer);

        $checker = new ProductChecker();
        $checker
            ->setNext(new CategoryChecker())
            ->setNext(new BrandChecker())
            ->setNext(new MerchantChecker());

        return $checker->handle($this->offer, $product, $allMerchantPriceSettings);
    }
}
