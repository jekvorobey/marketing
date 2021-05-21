<?php

namespace App\Services\Calculator;

use App\Models\Bonus\Bonus;
use App\Models\Discount\Discount;
use App\Models\Price\Price;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Logistics\Services\ListsService\ListsService;
use Greensight\Oms\Services\OrderService\OrderService;
use Illuminate\Support\Collection;
use Pim\Core\PimException;
use Pim\Dto\CategoryDto;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductByOfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\CategoryService\CategoryService;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;

/**
 * Class InputCalculator
 * @package App\Services\Calculator
 */
class InputCalculator
{
    /** @var Collection */
    public $bundles;
    /** @var Collection */
    public $offers;
    /** @var Collection */
    public $brands;
    /** @var Collection */
    public $categories;
    /** @var Collection */
    public $ticketTypeIds;
    /** @var string|null */
    public $promoCode;
    /** @var Discount|null */
    public $promoCodeDiscount;
    /** @var Bonus|null */
    public $promoCodeBonus;
    /** @var array */
    public $customer;
    /** @var array */
    public $payment;
    /** @var int */
    public $bonus;
    /** @var Collection */
    public $deliveries;
    /** @var bool */
    public $freeDelivery;

    /** @var array */
    public $userRegion;
    /** @var int */
    public $customerBonusAmount;
    /** @var Collection|CategoryDto[] */
    private static $allCategories;

    /**
     * InputPriceCalculator constructor.
     *
     * @param Collection|array $params
     *
     * @throws PimException
     */
    public function __construct($params)
    {
        $this->parse($params);
        $this->loadData();
    }

    /**
     * Получает все категории
     * @return Collection|CategoryDto[]
     * @throws PimException
     */
    public static function getAllCategories()
    {
        if (isset(static::$allCategories)) {
            return static::$allCategories;
        }

        $categoryService = resolve(CategoryService::class);
        static::$allCategories = $categoryService->categories($categoryService->newQuery())->keyBy('id');
        return static::$allCategories;
    }

    protected function parse($params)
    {
        $this->offers = isset($params['offers']) ? collect($params['offers']) : collect();
        $this->bundles = collect(); // todo
        $this->brands = collect();
        $this->categories = collect();
        $this->ticketTypeIds = collect();
        $this->promoCode = isset($params['promoCode']) ? (string) $params['promoCode'] : null;
        $this->userRegion = $this->getUserRegion($params['regionFiasId'] ?? null);
        $this->customer = [
            'id' => null,
            'roles' => [],
            'segment' => null,
        ];

        if (isset($params['customer'])) {
            $this->customer = [
                'id' => isset($params['customer']['id']) ? (int) $params['customer']['id'] : null,
                'roles' => $params['customer']['roles'] ?? [],
                'segment' => isset($params['customer']['segment']) ? (int) $params['customer']['segment'] : null,
            ];
            $this->customerBonusAmount = $this->loadCustomerBonusAmount($this->customer['id']) ?? 0;
        } else {
            if (isset($params['role_ids']) && is_array($params['role_ids'])) {
                $this->customer['roles'] = array_map(function ($roleId) {
                    return (int) $roleId;
                }, $params['role_ids']);
            }
            if (isset($params['segment_id'])) {
                $this->customer['segment'] = (int) $params['segment_id'];
            }
            $this->customerBonusAmount = 0;
        }

        $this->payment = [
            'method' => isset($params['payment']['method']) ? intval($params['payment']['method']) : null,
        ];
        $this->bonus = isset($params['bonus']) ? (int) $params['bonus'] : 0;

        /** Все возможные типы доставки */
        $this->deliveries = collect();
        $this->deliveries['notSelected'] = collect();
        /** Выбранный тип доставки */
        $this->deliveries->put('selected', null);
        /** Текущий тип доставки */
        $this->deliveries->put('current', null);
        /** Флаг бесплатной доставки */
        $this->freeDelivery = false;

        if (!isset($params['deliveries'])) {
            return;
        }

        if (is_iterable($params['deliveries'])) {
            $id = 0;
            $this->deliveries->put('items', collect());
            foreach ($params['deliveries'] as $delivery) {
                $id++;
                $this->deliveries['items']->put($id, [
                    'id' => $id,
                    'price' => isset($delivery['price']) ? intval($delivery['price']) : null,
                    'method' => isset($delivery['method']) ? intval($delivery['method']) : null,
                    'region' => isset($delivery['region']) ? intval($delivery['region']) : null,
                    'selected' => isset($delivery['selected']) ? boolval($delivery['selected']) : false,
                ]);

                if ($this->deliveries['items'][$id]['selected']) {
                    $this->deliveries['current'] = $this->deliveries['items'][$id];
                }
            }
        }

        /** Доставки, для которых необходимо посчитать только возможную скидку */
        $this->deliveries['notSelected'] = $this->deliveries['items']->filter(function ($delivery) {
            return !$delivery['selected'];
        });
    }

    protected function getUserRegion($userRegionFiasId)
    {
        if (!$userRegionFiasId) {
            return null;
        }

        return app(ListsService::class)->regions()->keyBy->guid->get($userRegionFiasId);
    }

    /**
     * Загружает все необходимые данные
     * @return $this
     * @throws PimException
     */
    protected function loadData()
    {
        $offerIds = $this->offers->pluck('id');
        if ($offerIds->isNotEmpty()) {
            $this->hydrateOffer();
            $this->hydrateOfferPrice();
            $this->hydrateProductInfo();
        } else {
            $this->offers = collect();
        }

        $this->bundles = $this->offers->pluck('bundles')
            ->map(function ($bundles) {
                return $bundles->keys();
            })
            ->collapse()
            ->unique()
            ->filter(function ($bundleId) {
                return $bundleId > 0;
            })
            ->values();

        $this->brands = $this->offers->pluck('brand_id')
            ->unique()
            ->filter(function ($brandId) {
                return $brandId > 0;
            })
            ->flip();

        $this->categories = $this->offers->pluck('category_id')
            ->unique()
            ->filter(function ($categoryId) {
                return $categoryId > 0;
            })
            ->flip();

        $this->ticketTypeIds = $this->offers->pluck('ticket_type_id')
            ->unique()
            ->filter(function ($ticketType) {
                return $ticketType > 0;
            })
            ->flip();

        if (isset($this->customer['id'])) {
            $this->customer = $this->getCustomerInfo((int) $this->customer['id']);
        }

        return $this;
    }

    /**
     * Заполняет информацию по офферам
     * @return $this
     * @throws PimException
     */
    protected function hydrateOffer()
    {
        $offerIds = $this->offers->pluck('id')->filter();

        if ($offerIds->isEmpty()) {
            return $this;
        }
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);

        $offersDto = $offerService->offers(
            (new RestQuery())
                ->setFilter('id', $offerIds)
                ->addFields(OfferDto::entity(), 'id', 'merchant_id', 'ticket_type_id')
        )->keyBy('id');
        $offers = collect();
        foreach ($this->offers as $offer) {
            if (!isset($offer['id'])) {
                continue;
            }

            $offerId = (int) $offer['id'];
            if (!$offersDto->has($offerId)) {
                continue;
            }
            /** @var OfferDto $offerDto */
            $offerDto = $offersDto->get($offerId);

            $offers->put($offerId, collect([
                'id' => $offerId,
                'price' => $offer['price'] ?? null,
                'qty' => $offer['qty'] ?? 1,
                'brand_id' => $offer['brand_id'] ?? null,
                'category_id' => $offer['category_id'] ?? null,
                'merchant_id' => $offerDto->merchant_id,
                'bundles' => $offer['bundles'] ?? collect(),
                'ticket_type_id' => $offerDto->ticket_type_id ?? null,
            ]));
        }
        $this->offers = $offers;

        return $this;
    }

    /**
     * Заполняет цены офферов
     * @return $this
     */
    protected function hydrateOfferPrice()
    {
        $offerIds = $this->offers->pluck('id');
        /** @var Collection $prices */
        $prices = Price::select(['offer_id', 'price'])
            ->whereIn('offer_id', $offerIds)
            ->get()
            ->pluck('price', 'offer_id');

        $offers = collect();
        foreach ($this->offers as $offer) {
            if (!isset($offer['id'])) {
                continue;
            }

            $offerId = (int) $offer['id'];
            if (!$prices->has($offerId)) {
                continue;
            }

            $offers->put($offerId, collect([
                'id' => $offerId,
                'price' => $prices[$offerId],
                'qty' => $offer['qty'] ?? 1,
                'brand_id' => $offer['brand_id'] ?? null,
                'category_id' => $offer['category_id'] ?? null,
                'merchant_id' => $offer['merchant_id'] ?? null,
                'bundles' => $offer['bundles'] ?? [],
                'ticket_type_id' => $offer['ticket_type_id'] ?? null,
            ]));
        }
        $this->offers = $offers;

        return $this;
    }

    /**
     * Заполняет информацию о товаре (категория, бренд)
     * @return $this
     */
    protected function hydrateProductInfo()
    {
        $offerIds = $this->offers->pluck('id')->toArray();
        /** @var ProductService $productService */
        $productService = resolve(ProductService::class);
        $productQuery = $productService
            ->newQuery()
            ->addFields(
                ProductDto::entity(),
                'id',
                'category_id',
                'brand_id'
            );

        $offers = collect();
        $productsByOffers = $productService->productsByOffers($productQuery, $offerIds);
        foreach ($this->offers as $offer) {
            $offerId = $offer['id'];
            /** @var ProductByOfferDto $product */
            $product = $productsByOffers->get($offerId);
            $offers->put($offerId, collect([
                'id' => $offerId,
                'price' => $offer['price'] ?? null,
                'qty' => $offer['qty'] ?? 1,
                'brand_id' => isset($product->product) ? $product->product->brand_id : null,
                'category_id' => isset($product->product) ? $product->product->category_id : null,
                'product_id' => isset($product->product) ? $product->product->id : null,
                'merchant_id' => $offer['merchant_id'] ?? null,
                'bundles' => $offer['bundles'] ?? [],
                'ticket_type_id' => $offer['ticket_type_id'] ?? null,
            ]));
        }
        $this->offers = $offers;

        return $this;
    }

    /**
     * @return array
     */
    protected function getCustomerInfo(int $customerId)
    {
        $customer = [
            'id' => $customerId,
            'roles' => [],
            'segment' => 1, // todo
            'orders' => [],
        ];

        $this->customer['id'] = $customerId;
        $customer['roles'] = $this->loadRoleForCustomer($customerId);
        if (!$customer['roles']) {
            return [];
        }

        $customer['orders']['count'] = $this->loadCustomerOrdersCount($customerId);

        return $customer;
    }

    /**
     * @return array|null
     */
    protected function loadRoleForCustomer(int $customerId)
    {
        /** @var CustomerService $customerService */
        $customerService = resolve(CustomerService::class);
        $query = $customerService->newQuery()
            ->addFields(CustomerDto::entity(), 'user_id')
            ->setFilter('id', $customerId);
        $customer = $customerService->customers($query)->first();
        if (!isset($customer['user_id'])) {
            return null;
        }

        /** @var UserService $userService */
        $userService = resolve(UserService::class);

        return $userService->userRoles($customer['user_id'])->pluck('id')->toArray();
    }

    /**
     * @return int
     */
    protected function loadCustomerOrdersCount(int $customerId)
    {
        /** @var OrderService $orderService */
        $orderService = resolve(OrderService::class);
        $query = $orderService->newQuery()->setFilter('customer_id', $customerId);
        $ordersCount = $orderService->ordersCount($query);

        return $ordersCount['total'];
    }

    protected function loadCustomerBonusAmount(int $customerId)
    {
        $customerService = resolve(CustomerService::class);
        $query = $customerService->newQuery()->setFilter('id', $customerId);
        $customer = $customerService->customers($query)->first();
        return $customer['bonus'] ?? 0;
    }

    /**
     * @return int|null
     */
    public function getCustomerId()
    {
        return $this->customer['id'] ?? null;
    }

    /**
     * @return int|null
     */
    public function getCountOrders()
    {
        return $this->customer['orders']['count'] ?? null;
    }

    /**
     * Сумма заказа без учета скидки
     * @return int
     */
    public function getCostOrders()
    {
        return $this->offers->map(function ($offer) {
            return ($offer['cost'] ?? $offer['price']) * $offer['qty'];
        })->sum();
    }

    /**
     * Заказ от определенной суммы
     * @return int
     */
    public function getPriceOrders()
    {
        return $this->offers->map(function ($offer) {
            return $offer['price'] * $offer['qty'];
        })->sum();
    }

    /**
     * @param array $categories
     * @return int
     */
    public function getMaxTotalPriceForCategories($categories)
    {
        $max = 0;
        foreach ($categories as $categoryId) {
            $sum = $this->offers->filter(function ($offer) use ($categoryId) {
                $allCategories = static::getAllCategories();
                return $allCategories->has($categoryId)
                    && $allCategories->has($offer['category_id'])
                    && $allCategories[$categoryId]->isSelfOrAncestorOf($allCategories[$offer['category_id']]);
            })->map(function ($offer) {
                return $offer['price'] * $offer['qty'];
            })->sum();
            $max = max($sum, $max);
        }

        return $max;
    }

    /**
     * Возвращает максимальную сумму товаров среди брендов ($brands)
     * @param array $brands
     * @return int
     */
    public function getMaxTotalPriceForBrands($brands)
    {
        $max = 0;
        foreach ($brands as $brandId) {
            $sum = $this->offers->filter(function ($offer) use ($brandId) {
                return (int) $offer['brand_id'] === (int) $brandId;
            })->map(function ($offer) {
                return $offer['price'] * $offer['qty'];
            })->sum();
            $max = max($sum, $max);
        }

        return $max;
    }
}
