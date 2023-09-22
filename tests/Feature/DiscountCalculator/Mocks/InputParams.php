<?php

namespace Tests\Feature\DiscountCalculator\Mocks;

use App\Models\Basket\BasketItem;
use Greensight\CommonMsa\Rest\RestQuery;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;

class InputParams
{
    protected int $basketItemsCount = 1;
    protected bool $withPublicEvents = false;

    /**
     * @return array
     * @throws PimException
     */
    public function baseParamsWithRandomBasketItems(): array
    {
        return [
            'customer' =>  [
                'id' => null
            ],
            'items' => [],
            'promoCode' => '',
            'deliveries' => [],
            'payment' =>  [
                'method' => 0
            ],
            'bonus' => 0,
            'regionFiasId' => null,
            'roleId' => '103',
            'basketItems' =>  $this->getBasketItems()
        ];
    }

    /**
     * @return array
     * @throws PimException
     */
    public function baseCheckoutParams(): array
    {
        return [
            'customer' => [
                'id' => 1
            ],
            'items' => [],
            'promoCode' => '',
            'deliveries' => [
                [
                    'method' => 1,
                    'price' => 749,
                    'region' => null,
                    'selected' => true,
                ],
                [
                    'method' => 2,
                    'price' => 499,
                    'region' => null,
                    'selected' => false,
                ]
            ],
            'payment' => [
                'method' => null,
            ],
            'bonus' => 1000,
            'regionFiasId' => null,
            'roleId' => '103',
            'basketItems' => $this->getBasketItems(),
        ];
    }
    /**
     * @return mixed
     * @throws PimException
     */
    protected function getBasketItems(): array
    {
        $query = $this->withPublicEvents
            ? (new RestQuery())->setFilter('product_id', 'null')
            : (new RestQuery())->include('product');

        $offers = app(OfferService::class)->offers(
            $query->pageNumber(1, $this->basketItemsCount)
        );

        return $offers->map(fn (OfferDto $offer) => new BasketItem(
            rand(1000, 10000),
            1,
            $offer->id,
            $offer->product?->category_id ?? 0,
            $offer->product?->brand_id ?? 0,
            0
        ))->all();
    }

    /**
     * @param int $basketItemsCount
     * @return $this
     */
    public function setBasketItemsCount(int $basketItemsCount): InputParams
    {
        $this->basketItemsCount = $basketItemsCount;
        return $this;
    }

    /**
     * @return $this
     */
    public function withPublicEvents(): InputParams
    {
        $this->withPublicEvents = true;
        return $this;
    }
}
