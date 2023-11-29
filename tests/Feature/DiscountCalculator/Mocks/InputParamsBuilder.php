<?php

namespace Tests\Feature\DiscountCalculator\Mocks;

use App\Models\Basket\BasketItem;
use Greensight\CommonMsa\Rest\RestQuery;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;

class InputParamsBuilder
{
    protected ?int $customerId = null;
    protected int $basketItemsCount = 1;
    protected bool $witDeliveries = false;
    protected bool $withPublicEvents = false;
    protected int $eachBasketItemCount = 1;
    private ?array $basketItemsMerchantIds = null;
    private ?array $exceptedBasketItemsMerchantIds = null;
    protected ?array $basketItemsBrandIds = null;
    protected ?array $basketItemsCategoryIds = null;
    protected ?array $productIds = null;
    protected ?int $paymentMethod = null;
    protected ?string $regionFiasId = null;

    /**
     * @throws PimException
     */
    public function build(): array
    {
        return [
            'customer' => [
                'id' => $this->customerId,
            ],
            'items' => [],
            'promoCode' => '',
            'deliveries' => $this->makeDeliveries(),
            'payment' => [
                'method' => $this->paymentMethod,
            ],
            'bonus' => 1000,
            'regionFiasId' => $this->regionFiasId,
            'roleId' => '103',
            'basketItems' => $this->getBasketItems(),
        ];
    }

    /**
     * @return array[]
     */
    protected function makeDeliveries(): array
    {
        if ($this->witDeliveries) {
            return [
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
            ];
        }

        return [];
    }

    /**
     * @param int $basketItemsCount
     * @return $this
     */
    public function setBasketItemsCount(int $basketItemsCount): InputParamsBuilder
    {
        $this->basketItemsCount = $basketItemsCount;
        return $this;
    }

    /**
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): InputParamsBuilder
    {
        $this->customerId = $customerId;
        return $this;
    }

    /**
     * @return mixed
     * @throws PimException
     */
    private function getBasketItems()
    {
        $query = $this->withPublicEvents
            ? (new RestQuery())->setFilter('product_id', 'null')
            : (new RestQuery())->include('product');

        if ($this->basketItemsMerchantIds) {
            $query->setFilter('merchant_id', 'in', $this->basketItemsMerchantIds);
        }

        if ($this->exceptedBasketItemsMerchantIds) {
            $query->setFilter('merchant_id', 'not_in', $this->exceptedBasketItemsMerchantIds);
        }

        if ($this->basketItemsBrandIds) {
            $query->setFilter('brand_id', 'in', $this->basketItemsBrandIds);
        }

        if ($this->productIds) {
            $query->setFilter('product_id', 'in', $this->productIds);
        }

        $offers = app(OfferService::class)->offers(
            $query->pageNumber(1, $this->basketItemsCount)
        );

        return $offers->map(fn (OfferDto $offer) => new BasketItem(
            rand(1000, 10000),
            $this->eachBasketItemCount,
            $offer->id,
            $offer->product?->category_id ?? 0,
            $offer->product?->brand_id ?? 0,
            0
        ))->all();
    }

    /**
     * Кол-во каждого товара в корзине
     * @param int $eachBasketItemCount
     * @return $this
     */
    public function setEachBasketItemCount(int $eachBasketItemCount): InputParamsBuilder
    {
        $this->eachBasketItemCount = $eachBasketItemCount;
        return $this;
    }

    /**
     * @param array|null $basketItemsMerchantIds
     * @return $this
     */
    public function setBasketItemsMerchantIds(?array $basketItemsMerchantIds): InputParamsBuilder
    {
        $this->basketItemsMerchantIds = $basketItemsMerchantIds;
        return $this;
    }

    /**
     * @param array|null $basketItemsBrandIds
     * @return $this
     */
    public function setBasketItemsBrandIds(?array $basketItemsBrandIds): InputParamsBuilder
    {
        $this->basketItemsBrandIds = $basketItemsBrandIds;
        return $this;
    }

    /**
     * @param array|null $basketItemsCategoryIds
     * @return $this
     */
    public function setBasketItemsCategoryIds(?array $basketItemsCategoryIds): InputParamsBuilder
    {
        $this->basketItemsCategoryIds = $basketItemsCategoryIds;
        return $this;
    }

    /**
     * @param int|null $paymentMethod
     * @return $this
     */
    public function setPaymentMethod(?int $paymentMethod): InputParamsBuilder
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    /**
     * @param string|null $regionFiasId
     * @return $this
     */
    public function setRegionFiasId(?string $regionFiasId): InputParamsBuilder
    {
        $this->regionFiasId = $regionFiasId;
        return $this;
    }

    /**
     * @param array|null $exceptedBasketItemsMerchantIds
     * @return $this
     */
    public function setExceptedBasketItemsMerchantIds(?array $exceptedBasketItemsMerchantIds): InputParamsBuilder
    {
        $this->exceptedBasketItemsMerchantIds = $exceptedBasketItemsMerchantIds;
        return $this;
    }

    /**
     * @param array|null $productIds
     * @return $this
     */
    public function setProductIds(?array $productIds): InputParamsBuilder
    {
        $this->productIds = $productIds;
        return $this;
    }
}
