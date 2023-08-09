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

    final public function handle(OfferDto $offer, ProductDto $product, Collection $merchantPricesSettings): ?MerchantPricesDto
    {
        $merchantPrice = $this->check($offer, $product, $merchantPricesSettings);

        if ($merchantPrice) {
            return $merchantPrice;
        }

        if ($this->next instanceof AbstractMerchantPriceChecker) {
            return $this->next->handle($offer, $product, $merchantPricesSettings);
        }

        return null;
    }

    abstract protected function check(OfferDto $offer, ProductDto $product, Collection $merchantPricesSettings): ?MerchantPricesDto;
}
