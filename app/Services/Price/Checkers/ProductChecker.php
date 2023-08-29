<?php

namespace App\Services\Price\Checkers;

use MerchantManagement\Dto\MerchantPricesDto;
use Pim\Dto\Offer\OfferDto;
use Illuminate\Support\Collection;
use Pim\Dto\Product\ProductDto;

class ProductChecker extends AbstractMerchantPriceChecker
{
    protected function check(OfferDto $offer, Collection $merchantPricesSettings): ?MerchantPricesDto
    {
        return $merchantPricesSettings->filter(fn(MerchantPricesDto $merchantPrice) =>
            $merchantPrice->type === MerchantPricesDto::TYPE_SKU
            && $merchantPrice->product_id
            && (int) $offer?->product->id === (int) $merchantPrice->product_id
        )->first() ?: null;
    }
}
