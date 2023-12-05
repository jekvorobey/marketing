<?php

namespace App\Services\Calculator;

use App\Models\Basket\BasketItem;
use App\Models\Bonus\Bonus;
use App\Models\Discount\Discount;
use App\Models\Price\Price;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Logistics\Services\ListsService\ListsService;
use Greensight\Oms\Dto\OrderStatus;
use Greensight\Oms\Services\OrderService\OrderService;
use Greensight\Oms\Services\PaymentService\PaymentService;
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
    public $brands;
    /** @var Collection */
    public $categories;
    /** @var Collection */
    public $ticketTypeIds;
    /** @var string|null */
    public $promoCode;
    /** @var Collection|Discount[]|null */
    public $promoCodeDiscounts;
    /** @var Bonus|null */
    public $promoCodeBonus;
    /** @var array */
    public $customer;
    /** @var int */
    public $roleId;
    /** @var string */
    public $regionFiasId;
    /** @var array */
    public $payment;
    /** @var int */
    public $bonus;
    /** @var Collection */
    public $deliveries;
    /** @var bool */
    public $freeDelivery;

    /** @var Collection|CategoryDto[] */
    private static $allCategories;
    private ?int $regionId = null;
    private static Collection $offersCache;

    /**
     * InputPriceCalculator constructor.
     */
    public function __construct(array|Collection $params)
    {
        $this->parse($params);
        $this->loadData();
    }

    /**
     * Получает все категории
     * @return Collection|CategoryDto[]
     * @throws PimException
     */
    public static function getAllCategories(): Collection
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
        $this->promoCodeDiscounts = new Collection();
        $this->regionFiasId = $params['regionFiasId'] ?? null;
        $this->roleId = $params['roleId'] ?? null;
        $this->customer = [
            'id' => null,
            'roles' => [],
            'segment' => null,
        ];

        if (isset($params['customer'])) {
            $this->customer = [
                'id' => $params['customer']['id'],
                'roles' => $params['customer']['roles'] ?? [],
                'segment' => isset($params['customer']['segment']) ? (int) $params['customer']['segment'] : null,
            ];
        } else {
            if (isset($params['role_ids']) && is_array($params['role_ids'])) {
                $this->customer['roles'] = array_map(static function ($roleId) {
                    return (int) $roleId;
                }, $params['role_ids']);
                $this->roleId = $params['role_ids'][0] ?? null;
            }
            if (isset($params['segment_id'])) {
                $this->customer['segment'] = (int) $params['segment_id'];
            }
        }

        $this->payment = [
            'method' => isset($params['payment']['method']) ? (int) $params['payment']['method'] : null,
            'isNeedCalculate' => true,
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
                    'selected' => isset($delivery['selected']) && $delivery['selected'],
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

    /**
     * Загружает все необходимые данные
     */
    protected function loadData(): self
    {
        $this->hydrateBasketItems();

        $this->bundles = $this->basketItems->pluck('bundle_id')
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

        $this->hydrateCustomerInfo();
        $this->hydratePaymentInfo();

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

        $hydratedBasketItems = collect();
        /** @var BasketItem $basketItem */
        foreach ($basketItems as $basketItem) {
            $offerId = $basketItem->offerId;

            /** @var OfferDto|null $offerDto */
            $offerDto = $offersDto->get($offerId);

            if (!$offerDto || !$prices->has($offerId)) {
                continue;
            }

            /** @var Price $basePrice */
            $basePrice = $prices->get($offerId);
            $priceByRole = $basePrice?->pricesByRoles->filter(fn($tmpPrice) => $tmpPrice->role == $this->roleId)->first();

            $currentPrice = $priceByRole->price ?? $basePrice->price ?? null;

            $hydratedBasketItems->put($basketItem->id, collect([
                'id' => $basketItem->id,
                'offer_id' => $offerId,
                'price' => $currentPrice,
                'price_base' => $basePrice->price ?? null,
                'prices_by_roles' => ($basePrice && $basePrice->pricesByRoles) ?
                    $basePrice->pricesByRoles->keyBy('role')->transform(fn($tmpPriceByRole) => [
                        'role' => $tmpPriceByRole->role,
                        'price' => $tmpPriceByRole->price,
                        'percent_by_base_price' => $tmpPriceByRole->percent_by_base_price,
                    ])->toArray() : null,
                'cost' => $currentPrice,
                'qty' => $basketItem->qty ?? 1,
                'brand_id' => $offerDto->product->brand_id ?? null,
                'category_id' => $offerDto->product->category_id ?? null,
                'additional_category_ids' => $offerDto->product
                    ?->additionalCategories
                    ->pluck('id')
                    ->toArray() ?? [],
                'product_id' => $offerDto->product_id,
                'merchant_id' => $offerDto->merchant_id,
                'bundle_id' => $basketItem->bundleId,
                'ticket_type_id' => $offerDto->ticket_type_id ?? null,
                'properties' => []
            ]));
        }

        $this->basketItems = $hydratedBasketItems;
    }

    /**
     * @throws PimException
     */
    protected function loadOffers(array $offersIds): Collection
    {
        if (!isset(static::$offersCache)) {
            static::$offersCache = collect();
        }

        $offersIds = array_diff($offersIds, self::$offersCache->keys()->toArray());

        if ($offersIds) {
            /** @var OfferService $offerService */
            $offerService = resolve(OfferService::class);
            $query = $offerService->newQuery()
                ->setFilter('id', $offersIds)
                ->include('product.additionalCategories')
                ->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id', 'ticket_type_id')
                ->addFields(ProductDto::entity(), 'id', 'category_id', 'brand_id');

            self::$offersCache = self::$offersCache->union($offerService->offers($query)->keyBy('id'));
        }

        return self::$offersCache;
    }

    /**
     * @throws PimException
     */
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
            ->with('pricesByRoles')
            ->whereIn('offer_id', $offersIds)
            ->get()
            ->keyBy('offer_id');
    }

    protected function hydrateCustomerInfo(): void
    {
        $customerId = $this->getCustomerId();
        if (!$customerId) {
            return;
        }

        $customer = $this->loadCustomer($customerId);
        if (!$customer) {
            return;
        }

        $roles = $this->loadCustomerUserRoles($customer);
        if (!$roles) {
            return;
        }

        $this->customer = [
            'id' => $customerId,
            'roles' => $roles,
            'segment' => 1, // todo
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

    protected function hydratePaymentInfo(): void
    {
        $paymentMethodId = $this->payment['method'];
        if (!$paymentMethodId) {
            return;
        }

        /** @var PaymentService $paymentService */
        $paymentService = resolve(PaymentService::class);

        $paymentMethod = $paymentService->getPaymentMethod($paymentMethodId);

        $this->payment = [
            'method' => $paymentMethodId,
            'isNeedCalculate' => $paymentMethod->is_apply_discounts,
        ];
    }

    public function getCustomerId(): ?int
    {
        return $this->customer['id'] ?? null;
    }

    public function getCustomerOrdersCount(): ?int
    {
        if (!$customerId = $this->getCustomerId()) {
            return null;
        }

        $this->customer['ordersCount'] ??= $this->loadCustomerOrdersCount($customerId);

        return $this->customer['ordersCount'];
    }

    protected function loadCustomerOrdersCount(int $customerId): int
    {
        /** @var OrderService $orderService */
        $orderService = resolve(OrderService::class);
        $query = $orderService->newQuery()
            ->setFilter('customer_id', $customerId)
            ->setFilter('status', [OrderStatus::DELIVERING, OrderStatus::READY_FOR_RECIPIENT, OrderStatus::DONE]);
        $ordersCount = $orderService->ordersCount($query);

        return (int) $ordersCount['total'];
    }

    public function getUserRegionId(): ?int
    {
        if (!$this->regionFiasId) {
            return null;
        }

        $this->regionId ??= $this->loadUserRegionId();

        return $this->regionId;
    }

    protected function loadUserRegionId(): ?int
    {
        /** @var ListsService $listsService */
        $listsService = resolve(ListsService::class);
        $query = $listsService->newQuery()
            ->setFilter('guid', $this->regionFiasId)
            ->addFields('id');

        return $listsService->regions($query)->first()->id ?? null;
    }

    /**
     * Сумма заказа без учета скидки
     */
    public function getOrderCost(): float
    {
        return $this->basketItems->map(function ($basketItem) {
            return ($basketItem['cost'] ?? $basketItem['price']) * $basketItem['qty'];
        })->sum();
    }

    /**
     * Сумма заказа со скидкой
     */
    public function getOrderPrice(): float
    {
        return $this->basketItems->map(function ($basketItem) {
            return $basketItem['price'] * $basketItem['qty'];
        })->sum();
    }

    public function getMaxTotalPriceForCategories(array $categories): int
    {
        $max = 0;
        $sum = 0;
        foreach ($categories as $categoryId) {
            $sum += $this->basketItems->filter(function ($basketItem) use ($categoryId) {
                $allCategories = static::getAllCategories();
                return $allCategories->has($categoryId)
                    && $allCategories->has($basketItem['category_id'])
                    && $allCategories[$categoryId]->isSelfOrAncestorOf($allCategories[$basketItem['category_id']]);
            })->map(function ($basketItem) {
                return $basketItem['price'] * $basketItem['qty'];
            })->sum();
        }

        return max($sum, $max);
    }

    /**
     * Возвращает максимальную сумму товаров среди брендов ($brands)
     */
    public function getMaxTotalPriceForBrands(array $brands): int
    {
        $max = 0;
        $sum = 0;
        foreach ($brands as $brandId) {
            $sum += $this->basketItems->filter(function ($basketItem) use ($brandId) {
                return (int) $basketItem['brand_id'] === (int) $brandId;
            })->map(function ($basketItem) {
                return $basketItem['price'] * $basketItem['qty'];
            })->sum();
        }

        return max($sum, $max);
    }


    /**
     * Установить текущей выбранную доставку
     * @return void
     */
    public function setSelectedDelivery(): void
    {
        $this->deliveries['current'] = $this->deliveries['items']
            ->filter(fn ($item) => $item['selected'])
            ->first();
    }

    /**
     * Есть ли доставки
     * @return bool
     */
    public function hasDeliveries(): bool
    {
        return !empty($this->deliveries['items']);
    }
}
