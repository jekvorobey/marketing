<?php

namespace App\Services\Price\Checkers;

use MerchantManagement\Dto\MerchantPricesDto;
use Pim\Dto\Offer\OfferDto;
use Illuminate\Support\Collection;
use Pim\Dto\Product\ProductDto;

class BrandChecker extends AbstractMerchantPriceChecker
{
    protected function check(OfferDto $offer, Collection $merchantPricesSettings): ?MerchantPricesDto
    {
        return $merchantPricesSettings->filter(fn(MerchantPricesDto $merchantPrice) =>
            $merchantPrice->type === MerchantPricesDto::TYPE_BRAND
            && $merchantPrice->brand_id
            && (int) $offer?->product?->brand_id === (int) $merchantPrice->brand_id
        )->first() ?: null;
    }
}
