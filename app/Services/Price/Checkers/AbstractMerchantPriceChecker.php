<?php

namespace App\Services\Price\Checkers;

use MerchantManagement\Dto\MerchantPricesDto;
use Illuminate\Support\Collection;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;

abstract class AbstractMerchantPriceChecker
{
    protected AbstractMerchantPriceChecker $next;

    final public function setNext(AbstractMerchantPriceChecker $nextChecker): AbstractMerchantPriceChecker
    {
        $this->next = $nextChecker;

        return $nextChecker;
    }

    final public function handle(OfferDto $offer, Collection $merchantPricesSettings): ?MerchantPricesDto
    {
        $this->loadProduct($offer);

        $merchantPrice = $this->check($offer, $merchantPricesSettings);

        if ($merchantPrice) {
            return $merchantPrice;
        }

        if ($this->next instanceof AbstractMerchantPriceChecker) {
            return $this->next->handle($offer, $merchantPricesSettings);
        }

        return null;
    }

    final protected function loadProduct(OfferDto $offer): void
    {
        if (isset($offer->product) && $offer->product instanceof ProductDto) {
            return;
        }

        $productsQuery = $this->productService->newQuery()
            ->setFilter('id', $this->offer->product_id)
            ->addFields(ProductDto::entity(), 'id', 'category_id', 'brand_id');

        $offer->product = $this->productService->products($productsQuery)->firstOrFail();
    }

    abstract protected function check(OfferDto $offer, Collection $merchantPricesSettings): ?MerchantPricesDto;
}
