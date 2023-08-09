<?php

namespace App\Services\Price\Checkers;

use MerchantManagement\Dto\MerchantPricesDto;
use Pim\Dto\Offer\OfferDto;
use Illuminate\Support\Collection;
use Pim\Dto\Product\ProductDto;

class CategoryChecker extends AbstractMerchantPriceChecker
{
    protected function check(OfferDto $offer, ProductDto $product, Collection $merchantPricesSettings): ?MerchantPricesDto
    {
        return $merchantPricesSettings->filter(fn(MerchantPricesDto $merchantPrice) =>
            $merchantPrice->type === MerchantPricesDto::TYPE_CATEGORY
            && (int) $product->category_id === (int) $merchantPrice->category_id
        )->first() ?: null;
    }
}
