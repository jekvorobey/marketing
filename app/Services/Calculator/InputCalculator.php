<?php

namespace App\Services\Calculator;

use App\Models\Basket\BasketItem;
use App\Models\Bonus\Bonus;
use App\Models\Discount\Discount;
use App\Models\Price\Price;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Logistics\Dto\Lists\RegionDto;
use Greensight\Logistics\Services\ListsService\ListsService;
use Greensight\Oms\Services\OrderService\OrderService;
use Illuminate\Support\Collection;
use Pim\Core\PimException;
use Pim\Dto\CategoryDto;
use Pim\Dto\Offer\OfferDto;
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
    public $basketItems;
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

        /** @var CategoryService $categoryService */
        $categoryService = resolve(CategoryService::class);
        $query = $categoryService->newQuery()->addFields(CategoryDto::entity(), 'id', '_lft', '_rgt');
        static::$allCategories = $categoryService->categories($query)->keyBy('id');

        return static::$allCategories;
    }

    protected function parse($params)
    {
        $this->basketItems = isset($params['basketItems']) ? collect($params['basketItems']) : collect();
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
            'isUserAuth' => false,
        ];

        if (isset($params['customer'])) {
            $this->customer = [
                'id' => $params['customer']['id'] ?? null,
                'roles' => $params['customer']['roles'] ?? [],
                'segment' => isset($params['customer']['segment']) ? (int) $params['customer']['segment'] : null,
                'isUserAuth' => $params['customer']['isUserAuth'],
            ];
        } else {
            if (isset($params['role_ids']) && is_array($params['role_ids'])) {
                $this->customer['roles'] = array_map(function ($roleId) {
                    return (int) $roleId;
                }, $params['role_ids']);
            }
            if (isset($params['segment_id'])) {
                $this->customer['segment'] = (int) $params['segment_id'];
            }
        }

        $this->payment = [
            'method' => isset($params['payment']['method']) ? (int) $params['payment']['method'] : null,
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
                    'price' => isset($delivery['price']) ? (int) $delivery['price'] : null,
                    'method' => isset($delivery['method']) ? (int) $delivery['method'] : null,
                    'region' => isset($delivery['region']) ? (int) $delivery['region'] : null,
                    'selected' => isset($delivery['selected']) ? (bool) $delivery['selected'] : false,
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

    protected function getUserRegion($userRegionFiasId): ?RegionDto
    {
        if (!$userRegionFiasId) {
            return null;
        }

        /** @var ListsService $listsService */
        $listsService = resolve(ListsService::class);
        $query = $listsService->newQuery()->setFilter('guid', $userRegionFiasId);

        return $listsService->regions($query)->first();
    }

    /**
     * Загружает все необходимые данные
     * @return $this
     * @throws PimException
     */
    protected function loadData()
    {
        $this->hydrateBasketItems();

        $this->bundles = $this->basketItems->pluck('bundles')
            ->map(function ($bundles) {
                return $bundles->keys();
            })
            ->collapse()
            ->unique()
            ->filter(function ($bundleId) {
                return $bundleId > 0;
            })
            ->values();

        $this->brands = $this->basketItems->pluck('brand_id')
            ->unique()
            ->filter(function ($brandId) {
                return $brandId > 0;
            })
            ->flip();

        $this->categories = $this->basketItems->pluck('category_id')
            ->unique()
            ->filter(function ($categoryId) {
                return $categoryId > 0;
            })
            ->flip();

        $this->ticketTypeIds = $this->basketItems->pluck('ticket_type_id')
            ->unique()
            ->filter(function ($ticketType) {
                return $ticketType > 0;
            })
            ->flip();

        if (isset($this->customer['id'], $this->customer['isUserAuth']) && $this->customer['isUserAuth']) {
            $this->customer = $this->getCustomerInfo((int) $this->customer['id']);
        }

        return $this;
    }

    protected function hydrateBasketItems(): void
    {
        $basketItems = $this->basketItems->filter(fn(BasketItem $basketItem) => isset($basketItem->offerId));
        if ($basketItems->isEmpty()) {
            return;
        }

        $offerIds = $this->basketItems->pluck('offerId')->all();

        $offersDto = $this->loadOffers($offerIds);
        $prices = $this->loadPrices($offerIds);
        $productsDto = $this->loadProducts($offersDto->pluck('product_id')->all());

        $hydratedBasketItems = collect();
        /** @var BasketItem $basketItem */
        foreach ($basketItems as $basketItem) {
            $offerId = (int) $basketItem->offerId;

            /** @var OfferDto|null $offerDto */
            $offerDto = $offersDto->get($offerId);

            if (!$offerDto || !$prices->has($offerId)) {
                continue;
            }

            /** @var float|null $price */
            $price = $prices->get($offerId);

            /** @var ProductDto|null $productDto */
            $productDto = $productsDto->get($offerDto->product_id);

            $bundleQty = collect([$basketItem])->keyBy('bundleId')
                ->map(function ($item) {
                    return collect([
                        'qty' => $item->qty,
                    ]);
                });

            $qty = $bundleQty->pluck('qty')->sum();

            $hydratedBasketItems->put($basketItem->id, collect([
                'id' => $basketItem->id,
                'offer_id' => $offerId,
                'price' => $price ?? null,
                'qty' => $qty ?? 1,
                'brand_id' => $productDto->brand_id ?? null,
                'category_id' => $productDto->category_id ?? null,
                'product_id' => $productDto->id ?? null,
                'merchant_id' => $offerDto->merchant_id,
                'bundle_id' => $basketItem->bundleId,
                'bundles' => $bundleQty,
                'ticket_type_id' => $offerDto->ticket_type_id ?? null,
            ]));
        }

        $this->basketItems = $hydratedBasketItems;
    }

    protected function loadOffers(array $offersIds): Collection
    {
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        $query = $offerService->newQuery()
            ->setFilter('id', $offersIds)
            ->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id', 'ticket_type_id');

        return $offerService->offers($query)->keyBy('id');
    }

    protected function loadProducts(array $productsIds): Collection
    {
        /** @var ProductService $productService */
        $productService = resolve(ProductService::class);
        $query = $productService->newQuery()
            ->addFields(ProductDto::entity(), 'id', 'category_id', 'brand_id')
            ->setFilter('id', $productsIds);

        return $productService->products($query)->keyBy('id');
    }

    protected function loadPrices(array $offersIds): Collection
    {
        return Price::query()
            ->whereIn('offer_id', $offersIds)
            ->pluck('price', 'offer_id');
    }

    protected function getCustomerInfo(int $customerId): array
    {
        $customer = $this->loadCustomer($customerId);
        if (!$customer) {
            return [];
        }

        $roles = $this->loadCustomerUserRoles($customer);
        if (!$roles) {
            return [];
        }

        $ordersCount = $this->loadCustomerOrdersCount($customerId);

        return [
            'id' => $customer['id'],
            'roles' => $roles,
            'segment' => 1, // todo
            'orders' => [
                'count' => $ordersCount,
            ],
            'bonus' => $customer['bonus'] ?? 0,
        ];
    }

    protected function loadCustomer(int $customerId): ?CustomerDto
    {
        /** @var CustomerService $customerService */
        $customerService = resolve(CustomerService::class);
        $query = $customerService->newQuery()
            ->addFields(CustomerDto::entity(), 'id', 'user_id', 'bonus')
            ->setFilter('id', $customerId);

        return $customerService->customers($query)->first();
    }

    protected function loadCustomerUserRoles(CustomerDto $customer): ?array
    {
        /** @var UserService $userService */
        $userService = resolve(UserService::class);

        return $userService->userRoles($customer->user_id)->pluck('id')->toArray();
    }

    protected function loadCustomerOrdersCount(int $customerId): int
    {
        /** @var OrderService $orderService */
        $orderService = resolve(OrderService::class);
        $query = $orderService->newQuery()->setFilter('customer_id', $customerId);
        $ordersCount = $orderService->ordersCount($query);

        return (int) $ordersCount['total'];
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
        return $this->basketItems->map(function ($basketItem) {
            return ($basketItem['cost'] ?? $basketItem['price']) * $basketItem['qty'];
        })->sum();
    }

    /**
     * Заказ от определенной суммы
     * @return int
     */
    public function getPriceOrders()
    {
        return $this->basketItems->map(function ($basketItem) {
            return $basketItem['price'] * $basketItem['qty'];
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
            $sum = $this->basketItems->filter(function ($basketItem) use ($categoryId) {
                $allCategories = static::getAllCategories();
                return $allCategories->has($categoryId)
                    && $allCategories->has($basketItem['category_id'])
                    && $allCategories[$categoryId]->isSelfOrAncestorOf($allCategories[$basketItem['category_id']]);
            })->map(function ($basketItem) {
                return $basketItem['price'] * $basketItem['qty'];
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
            $sum = $this->basketItems->filter(function ($basketItem) use ($brandId) {
                return (int) $basketItem['brand_id'] === (int) $brandId;
            })->map(function ($basketItem) {
                return $basketItem['price'] * $basketItem['qty'];
            })->sum();
            $max = max($sum, $max);
        }

        return $max;
    }
}
