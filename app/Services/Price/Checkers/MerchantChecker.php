<?php

namespace App\Services\Price\Checkers;

use MerchantManagement\Dto\MerchantPricesDto;
use Pim\Dto\Offer\OfferDto;
use Illuminate\Support\Collection;
use Pim\Dto\Product\ProductDto;

class MerchantChecker extends AbstractMerchantPriceChecker
{
    protected function check(OfferDto $offer, Collection $merchantPricesSettings): ?MerchantPricesDto
    {
        return $merchantPricesSettings->filter(fn(MerchantPricesDto $merchantPrice) =>
            $merchantPrice->type === MerchantPricesDto::TYPE_MERCHANT
            && (int) $offer->merchant_id === (int) $merchantPrice->merchant_id
        )->first() ?: null;
    }
}
