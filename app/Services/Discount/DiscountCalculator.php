<?php

namespace App\Services\Discount;

use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCategory;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountOffer;
use App\Models\Discount\DiscountSegment;
use App\Models\Discount\DiscountUserRole;
use App\Models\Price\Price;
use App\Models\Discount\Discount;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Oms\Services\OrderService\OrderService;
use Pim\Dto\CategoryDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\CategoryService\CategoryService;
use Pim\Services\ProductService\ProductService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Illuminate\Support\Collection;

/**
 * Class DiscountCalculator
 * @package App\Core\Discount
 */
class DiscountCalculator
{
    /**
     * Входные условия, влияющие на получения скидки
     * @var array
     */
    protected $filter;

    /**
     * Скидки, которые активированы с помощью промокода
     * @var Collection
     */
    protected $appliedDiscounts;

    /**
     * Данные подгружаемые из зависимостей Discount
     * @var Collection|Collection[]
     */
    protected $relations;

    /**
     * Список активных скидок
     * @var Collection
     */
    protected $discounts;

    /**
     * Список скидок, которые можно применить (отдельно друг от друга)
     * @var Collection
     */
    protected $possibleDiscounts;

    /**
     * Список категорий
     * @var CategoryDto[]|Collection
     */
    protected $categories;

    /**
     * DiscountCalculator constructor.
     * @param Collection $params
     * Формат:
     *  {
     *      'customer': ['id' => int],
     *      'offers': [['id' => int, 'qty' => int|null], ...]]
     *      'promoCode': todo
     *      'deliveries': [['method' => int, 'price' => int, 'region' => int, 'selected' => bool], ...]
     *      'payment': ['method' => int]
     *  }
     */
    public function __construct(Collection $params)
    {
        $this->filter = [];
        $this->filter['bundles'] = []; // todo
        $this->filter['offers'] = $params['offers'] ?? collect();
        $this->filter['promoCode'] = $params['promoCode'] ?? collect();
        $this->filter['customer'] = [
            'id' => isset($params['customer']['id']) ? intval($params['customer']['id']) : null
        ];
        $this->filter['payment'] = [
            'method' => isset($params['payment']['method']) ? intval($params['payment']['method']) : null
        ];

        /** Все возможные типы доставки */
        $this->filter['deliveries'] = collect();
        if (is_iterable($params['deliveries'])) {
            $id = 0;
            foreach ($params['deliveries'] as $delivery) {
                $id++;
                $this->filter['deliveries']->put($id, [
                    'id' => $id,
                    'price' => isset($delivery['price']) ? intval($delivery['price']) : null,
                    'method' => isset($delivery['method']) ? intval($delivery['method']) : null,
                    'region' => isset($delivery['region']) ? intval($delivery['region']) : null,
                    'selected' => isset($delivery['selected']) ? boolval($delivery['selected']) : false,
                ]);
            }
        }

        /** Доставки, для которых необходимо посчитать только возможную скидку */
        $this->filter['notSelectedDeliveries'] = $this->filter['deliveries']->filter(function ($delivery) {
            return !$delivery['selected'];
        });

        $this->discounts = collect();
        $this->possibleDiscounts = collect();
        $this->relations = collect();
        $this->categories = collect();
        $this->appliedDiscounts = collect();
        $this->loadData();
    }

    /**
     * Возвращает данные о примененных скидках
     *
     * @return array
     */
    public function calculate()
    {
        $calculator = $this->getActiveDiscounts()->fetchData();

        /**
         * Применяем скидки при заданной доставке,
         * сохраняем только скидку на заданную доставку,
         * затем откатываем изменения
         */
        foreach ($this->filter['notSelectedDeliveries'] as $delivery) {
            $deliveryId = $delivery['id'];
            $calculator->filter($delivery)->sort()->apply();
            $deliveryWithDiscount = $this->filter['deliveries'][$deliveryId];
            $this->rollback();
            $this->filter['deliveries'][$deliveryId] = $deliveryWithDiscount;
        }

        $calculator->filter()->sort()->apply();

        return [
            'discounts' => $this->getExternalDiscountFormat($this->appliedDiscounts),
            'offers' => $this->filter['offers'],
            'deliveries' => $this->filter['deliveries']->values(),
        ];
    }

    /**
     * @param $discounts
     * @return array
     */
    public function getExternalDiscountFormat($discounts)
    {
        $format = [];
        foreach ($discounts as $discount) {
            $conditions = $this->relations['conditions']->has($discount['id'])
                ? $this->relations['conditions'][$discount['id']]->toArray()
                : [];

            $extType = Discount::getExternalFormat($discount['type'], $conditions, false);
            $format[$extType] = isset($format[$extType])
                ? ($format[$extType] + $discount['value'])
                : $discount['value'];
        }
        return $format;
    }

    /**
     * Загружает все необходимые данные
     * @return $this
     */
    protected function loadData()
    {
        $this->fetchCategories();
        $offerIds = $this->filter['offers']->pluck('id');
        if ($offerIds->isNotEmpty()) {
            $this->hydrateOfferPrice();
            $this->hydrateProductInfo();
        } else {
            $this->filter['offers'] = collect();
        }

        $this->filter['brands'] = $this->filter['offers']->pluck('brand_id')
            ->unique()
            ->filter(function ($brandId) {
                return $brandId > 0;
            });

        $this->filter['categories'] = $this->filter['offers']->pluck('category_id')
            ->unique()
            ->filter(function ($brandId) {
                return $brandId > 0;
            });

        if (isset($this->filter['customer']['id'])) {
            $this->filter['customer'] = $this->getCustomerInfo((int)$this->filter['customer']['id']);
        }

        return $this;
    }

    /**
     * @param int $customerId
     * @return array
     */
    protected function getCustomerInfo(int $customerId)
    {
        $customer = [
            'id' => $customerId,
            'roles' => [],
            'segment' => 1, // todo
            'orders' => []
        ];

        $this->filter['customer']['id'] = $customerId;
        $customer['roles'] = $this->loadRoleForCustomer($customerId);
        if (!$customer['roles']) {
            return [];
        }

        $customer['orders']['count'] = $this->loadCustomerOrdersCount($customerId);
        return $customer;
    }

    /**
     * @param int $customerId
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
     * @param int $customerId
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

    /**
     * Заполняет цены офферов
     * @return $this
     */
    protected function hydrateOfferPrice()
    {
        $offerIds = $this->filter['offers']->pluck('id');
        /** @var Collection $prices */
        $prices = Price::select(['offer_id', 'price'])
            ->whereIn('offer_id', $offerIds)
            ->get()
            ->pluck('price', 'offer_id');

        $offers = collect();
        foreach ($this->filter['offers'] as $offer) {
            if (!isset($offer['id'])) {
                continue;
            }

            $offerId = (int)$offer['id'];
            if (!$prices->has($offerId)) {
                continue;
            }

            $offers->put($offerId, collect([
                'id' => $offerId,
                'price' => $prices[$offerId],
                'qty' => $offer['qty'] ?? 1,
                'brand_id' => $offer['brand_id'] ?? null,
                'category_id' => $offer['category_id'] ?? null,
            ]));
        }
        $this->filter['offers'] = $offers;
        return $this;
    }

    /**
     * Заполняет информацию о товаре (категория, бренд)
     * @return $this
     */
    protected function hydrateProductInfo()
    {
        $offerIds = $this->filter['offers']->pluck('id')->toArray();
        /** @var ProductService $offerService */
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
        foreach ($this->filter['offers'] as $offer) {
            $offerId = $offer['id'];
            if (!isset($productsByOffers[$offerId]['product'])) {
                continue;
            }

            $product = $productsByOffers[$offerId]['product'];
            $offers->put($offerId, collect([
                'id' => $offerId,
                'price' => $offer['price'] ?? null,
                'qty' => $offer['qty'] ?? 1,
                'brand_id' => $product['brand_id'],
                'category_id' => $product['category_id'],
            ]));
        }
        $this->filter['offers'] = $offers;

        return $this;
    }

    /**
     * Получить все активные скидки
     *
     * @return $this
     */
    protected function getActiveDiscounts()
    {
        $this->discounts = Discount::select(['id', 'type', 'value', 'value_type', 'promo_code_only'])
            ->active()
            ->orderBy('promo_code_only')
            ->orderBy('type')
            ->get();

        return $this;
    }

    /**
     * Загружает необходимые данные о полученных скидках ($this->discount)
     * @return $this
     */
    protected function fetchData()
    {
        $this->fetchDiscountOffers()
            ->fetchDiscountBrands()
            ->fetchDiscountCategories()
            ->fetchDiscountSegments()
            ->fetchDiscountCustomerRoles();
        return $this;
    }

    /**
     * Фильтрует все актуальные скидки и оставляет только те, которые можно применить
     *
     * @param array|null $currentDelivery
     * @return $this
     */
    protected function filter($currentDelivery = null)
    {
        $this->filter['delivery'] = $currentDelivery ??
            $this->filter['deliveries']->filter(function ($delivery) {
                return $delivery['selected'];
            })->first();


        $this->possibleDiscounts = $this->discounts->filter(function (Discount $discount) {
            return $this->checkDiscount($discount);
        })->values();

        $discountIds = $this->possibleDiscounts->pluck('id');
        $this->fetchDiscountConditions($discountIds);
        $this->possibleDiscounts = $this->possibleDiscounts->filter(function (Discount $discount) {
            if ($this->relations['conditions']->has($discount->id)) {
                return $this->checkConditions($this->relations['conditions'][$discount->id]);
            }
            return true;
        })->values();

        return $this;
    }

    /**
     * Сортирует скидки согласно приоритету
     * Скидки, имеющие наибольший приоритет помещаются в начало списка
     *
     * @return $this
     * @todo
     */
    protected function sort()
    {
        /**
         * На данный момент скидки сортируются по типу скидки
         * Если две скидки имеют одинаковый тип, то сначала берется первая по списку
         */
        return $this;
    }

    /**
     * Откатывает все примененные скидки
     * @return $this
     */
    protected function rollback()
    {
        $this->appliedDiscounts = collect();

        $offers = collect();
        foreach ($this->filter['offers'] as $offer) {
            $offer['price'] = $offer['cost'] ?? $offer['price'];
            unset($offer['discount']);
            unset($offer['cost']);
            $offers->put($offer['id'], $offer);
        }
        $this->filter['offers'] = $offers;

        $deliveries = collect();
        foreach ($this->filter['deliveries'] as $delivery) {
            $delivery['price'] = $delivery['cost'] ?? $delivery['price'];
            unset($delivery['discount']);
            unset($delivery['cost']);
            $deliveries->put($delivery['id'], $delivery);
        }
        $this->filter['deliveries'] = $deliveries;

        return $this;
    }

    /**
     * Применяет скидки
     *
     * @return $this
     */
    protected function apply()
    {
        /** @var Discount $discount */
        foreach ($this->possibleDiscounts as $discount) {
            if (!$this->isCompatible($discount)) {
                continue;
            }

            $changed = false;
            switch ($discount->type) {
                case Discount::DISCOUNT_TYPE_OFFER:
                    # Скидка на офферы
                    $offerIds = $this->relations['offers'][$discount->id]->pluck('offer_id');
                    $changed = $this->applyDiscountToOffer($discount, $offerIds);
                    break;
                case Discount::DISCOUNT_TYPE_BUNDLE:
                    // todo
                    break;
                case Discount::DISCOUNT_TYPE_BRAND:
                    # Скидка на бренды
                    /** @var Collection $brandIds */
                    $brandIds = $this->relations['brands'][$discount->id]->pluck('brand_id');
                    # За исключением офферов
                    $exceptOfferIds = $this->getExceptOffersForDiscount($discount->id);
                    # Отбираем нужные офферы
                    $offerIds = $this->filterForDiscountBrand($brandIds, $exceptOfferIds);
                    $changed = $this->applyDiscountToOffer($discount, $offerIds);
                    break;
                case Discount::DISCOUNT_TYPE_CATEGORY:
                    # Скидка на категории
                    /** @var Collection $categoryIds */
                    $categoryIds = $this->relations['categories'][$discount->id]->pluck('category_id');
                    # За исключением брендов
                    $exceptBrandIds = $this->getExceptBrandsForDiscount($discount->id);
                    # За исключением офферов
                    $exceptOfferIds = $this->getExceptOffersForDiscount($discount->id);
                    # Отбираем нужные офферы
                    $offerIds = $this->filterForDiscountCategory($categoryIds, $exceptBrandIds, $exceptOfferIds);
                    $changed = $this->applyDiscountToOffer($discount, $offerIds);
                    break;
                case Discount::DISCOUNT_TYPE_DELIVERY:
                    $deliveryId = $this->filter['delivery']['id'] ?? null;
                    if ($this->filter['deliveries']->has($deliveryId)) {
                        $changed = $this->changePrice(
                            $this->filter['delivery'],
                            $discount->value,
                            $discount->value_type
                        );

                        $this->filter['deliveries'][$deliveryId] = $this->filter['delivery'];
                    }

                    break;
                case Discount::DISCOUNT_TYPE_CART_TOTAL:
                    $changed = $this->applyDiscountToBasket($discount);
                    break;
            }

            if ($changed > 0) {
                $this->appliedDiscounts->push([
                    'id' => $discount->id,
                    'type' => $discount->type,
                    'value' => $changed,
                    'conditions' => $this->relations['conditions']->has($discount->id)
                        ? $this->relations['conditions'][$discount->id]->pluck('type')
                        : [],
                ]);
            }
        }

        return $this;
    }

    /**
     * Применяет скидку ко всей корзине (распределяется равномерно по всем товарам)
     *
     * @param $discount
     * @return int Абсолютный размер скидки (в руб.), который удалось использовать
     */
    protected function applyDiscountToBasket($discount)
    {
        if ($this->filter['offers']->isEmpty()) {
            return false;
        }

        return $this->applyEvenly($discount, $this->filter['offers']->pluck('id'));
    }

    /**
     * Равномерно распределяет скидку
     * @param $discount
     * @param Collection $offerIds
     * @return int Абсолютный размер скидки (в руб.), который удалось использовать
     */
    protected function applyEvenly($discount, Collection $offerIds)
    {
        $priceOrders = $this->getCostOrders();
        if ($priceOrders <= 0) {
            return 0.;
        }

        # Текущее значение скидки (в рублях, без учета скидок, которые могли применяться ранее)
        $currentDiscountValue = 0;
        # Номинальное значение скидки (в рублях)
        $discountValue = $discount->value_type === Discount::DISCOUNT_VALUE_TYPE_PERCENT
            ? round($priceOrders * $discount->value / 100)
            : $discount->value;
        # Скидка не может быть больше, чем стоимость всей корзины
        $discountValue = min($discountValue, $priceOrders);

        /**
         * Если скидку невозможно распределить равномерно по всем товарам,
         * то использовать скидку, сверх номинальной
         */
        $force = false;
        $prevCurrentDiscountValue = 0;
        while ($currentDiscountValue < $discountValue || $priceOrders === 0) {
            /**
             * Сортирует ID офферов.
             * Сначала применяем скидки на самые дорогие товары (цена * количество)
             * Если необходимо использовать скидку сверх номинальной ($force), то сортируем в обратном порядке.
             */
            $offerIds = $this->sortOrderIdsByTotalPrice($offerIds, $force);
            $coefficient = ($discountValue - $currentDiscountValue) / $priceOrders;
            foreach ($offerIds as $offerId) {
                $offer = &$this->filter['offers'][$offerId];
                $valueUp = ceil($offer['price'] * $coefficient);
                $valueDown = floor($offer['price'] * $coefficient);
                $changeUp = $this->changePrice($offer, $valueUp, $discount->value_type, false);
                $changeDown = $this->changePrice($offer, $valueDown, $discount->value_type, false);
                if ($changeUp * $offer['qty'] <= $discountValue - $currentDiscountValue || $force) {
                    $change = $this->changePrice($offer, $valueUp, $discount->value_type);
                } elseif ($changeDown * $offer['qty'] <= $discountValue - $currentDiscountValue || $force) {
                    $change = $this->changePrice($offer, $valueDown, $discount->value_type);
                } else {
                    continue;
                }

                $currentDiscountValue += $change * $offer['qty'];
                if ($currentDiscountValue >= $discountValue) {
                    break(2);
                }
            }

            $priceOrders = $this->getPriceOrders();
            if ($prevCurrentDiscountValue === $currentDiscountValue) {
                if ($force) {
                    break;
                }

                $force = true;
            }

            $prevCurrentDiscountValue = $currentDiscountValue;
        }

        return $currentDiscountValue;
    }

    /**
     * @param Collection $offerIds
     * @param bool $asc
     * @return Collection
     */
    protected function sortOrderIdsByTotalPrice(Collection $offerIds, $asc = true)
    {
        return $offerIds->sort(function ($offerIdLft, $offerIdRgt) use ($asc) {
            $offerLft = $this->filter['offers'][$offerIdLft];
            $totalPriceLft = $offerLft['price'] * $offerLft['qty'];
            $offerRgt = $this->filter['offers'][$offerIdRgt];
            $totalPriceRgt = $offerRgt['price'] * $offerRgt['qty'];
            return ($asc ? 1 : -1) * ($totalPriceLft - $totalPriceRgt);
        });
    }

    /**
     * Вместо равномерного распределения скидки по офферам (applyEvenly), применяет скидку к каждому офферу
     *
     * @param $discount
     * @param Collection $offerIds
     * @return bool Результат применения скидки
     */
    protected function applyDiscountToOffer($discount, Collection $offerIds)
    {
        if ($offerIds->isEmpty()) {
            return false;
        }

        $changed = 0;
        foreach ($offerIds as $offerId) {
            $offer = &$this->filter['offers'][$offerId];
            $changed += $this->changePrice($offer, $discount->value, $discount->value_type) * $offer['qty'];
        }

        return $changed;
    }

    /**
     * Возвращает размер скидки (без учета предыдущих скидок)
     * @param $item
     * @param $value
     * @param int $valueType
     * @param bool $apply нужно ли применять скидку
     * @return int
     */
    protected function changePrice(&$item, $value, $valueType = Discount::DISCOUNT_VALUE_TYPE_RUB, $apply = true)
    {
        if (!isset($item['price']) || $value <= 0) {
            return 0;
        }

        $currentDiscount = $item['discount'] ?? 0;
        $currentCost = $item['cost'] ?? $item['price'];
        switch ($valueType) {
            case Discount::DISCOUNT_VALUE_TYPE_PERCENT:
                $discountValue = min($item['price'], round($currentCost * $value / 100));
                break;
            case Discount::DISCOUNT_VALUE_TYPE_RUB:
                $discountValue = $value > $item['price'] ? $item['price'] : $value;
                break;
            default:
                return 0;
        }

        /** Цена не может быть меньше 1 рубля */
        if ($item['price'] - $discountValue < 1) {
            $discountValue = $item['price'] - 1;
        }

        if ($apply) {
            $item['discount'] = $currentDiscount + $discountValue;
            $item['price'] = $currentCost - $item['discount'];
            $item['cost'] = $currentCost;
        }

        return $discountValue;
    }

    /**
     * @param $brandIds
     * @param $exceptOfferIds
     * @return Collection
     */
    protected function filterForDiscountBrand($brandIds, $exceptOfferIds)
    {
        return $this->filter['offers']->filter(function ($offer) use ($brandIds, $exceptOfferIds) {
            return $brandIds->search($offer['brand_id']) !== false
                && $exceptOfferIds->search($offer['id']) === false;
        })->pluck('id');
    }

    /**
     * @param $categoryIds
     * @param $exceptBrandIds
     * @param $exceptOfferIds
     * @return Collection
     */
    protected function filterForDiscountCategory($categoryIds, $exceptBrandIds, $exceptOfferIds)
    {
        return $this->filter['offers']->filter(function ($offer) use ($categoryIds, $exceptBrandIds, $exceptOfferIds) {
            $offerCategory = $this->categories->has($offer['category_id'])
                ? $this->categories[$offer['category_id']]
                : null;

            return $offerCategory
                && $categoryIds->reduce(function ($carry, $categoryId) use ($offerCategory) {
                    return $carry ||
                        (
                            $this->categories->has($categoryId)
                            && $this->categories[$categoryId]->isAncestorOf($offerCategory)
                        );
                })
                && $exceptBrandIds->search($offer['brand_id']) === false
                && $exceptOfferIds->search($offer['id']) === false;
        })->pluck('id');
    }

    /**
     * @param Discount $discount
     * @return bool
     */
    protected function isCompatible(Discount $discount)
    {
        if ($this->appliedDiscounts->isEmpty()) {
            return true;
        }

        if (!$this->relations['conditions']->has($discount->id)) {
            return false;
        }

        $discountIds = $this->appliedDiscounts->pluck('id');

        /** @var DiscountCondition $condition */
        foreach ($this->relations['conditions'][$discount->id] as $condition) {
            if ($condition->type === DiscountCondition::DISCOUNT_SYNERGY) {
                $synergyDiscountIds = $condition->getSynergy();
                return $discountIds->intersect($synergyDiscountIds)->count() === $discountIds->count();
            }
        }

        return false;
    }

    /**
     * Получаем все возможные скидки и офферы из DiscountOffer
     * @return $this
     */
    protected function fetchDiscountOffers()
    {
        $validTypes = [Discount::DISCOUNT_TYPE_OFFER, Discount::DISCOUNT_TYPE_BRAND, Discount::DISCOUNT_TYPE_CATEGORY];
        if ($this->filter['offers']->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['offers'] = collect();
            return $this;
        }

        $this->relations['offers'] = DiscountOffer::select(['discount_id', 'offer_id', 'except'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->whereIn('offer_id', $this->filter['offers']->pluck('id'))
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получаем все возможные скидки и бренды из DiscountBrand
     * @return $this
     */
    protected function fetchDiscountBrands()
    {
        /** Если не передали офферы, то пропускаем скидки на бренды */
        $validTypes = [Discount::DISCOUNT_TYPE_BRAND, Discount::DISCOUNT_TYPE_CATEGORY];
        if ($this->filter['brands']->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['brands'] = collect();
            return $this;
        }

        $this->relations['brands'] = DiscountBrand::select(['discount_id', 'brand_id', 'except'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->whereIn('brand_id', $this->filter['brands'])
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получаем все возможные скидки и категории из DiscountCategory
     * @return $this
     */
    protected function fetchDiscountCategories()
    {
        /** Если не передали офферы, то пропускаем скидки на категорию */
        $validTypes = [Discount::DISCOUNT_TYPE_CATEGORY];
        if ($this->filter['categories']->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['categories'] = collect();
            return $this;
        }

        $this->relations['categories'] = DiscountCategory::select(['discount_id', 'category_id'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->get()
            ->filter(function ($discountCategory) {
                $categoryLeaf = $this->categories[$discountCategory->category_id];
                foreach ($this->filter['categories'] as $categoryId) {
                    if ($categoryLeaf->isSelfOrAncestorOf($this->categories[$categoryId])) {
                        return true;
                    }
                }
                return false;
            })
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получаем все возможные скидки и сегменты из DiscountSegment
     * @return $this
     */
    protected function fetchDiscountSegments()
    {
        if (!isset($this->filter['customer']['segment'])) {
            $this->relations['segments'] = collect();
            return $this;
        }

        $this->relations['segments'] = DiscountSegment::select(['discount_id', 'segment_id'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->get()
            ->groupBy('discount_id')
            ->transform(function ($segments) {
                return $segments->pluck('segment_id');
            });

        return $this;
    }

    /**
     * Получаем все возможные скидки и роли из DiscountRole
     * @return $this
     */
    protected function fetchDiscountCustomerRoles()
    {
        $this->relations['roles'] = DiscountUserRole::select(['discount_id', 'role_id'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->get()
            ->groupBy('discount_id')
            ->transform(function ($segments) {
                return $segments->pluck('role_id');
            });

        return $this;
    }

    /**
     * Получаем все возможные скидки и условия из DiscountCondition
     * @param Collection $discountIds
     * @return $this
     */
    protected function fetchDiscountConditions(Collection $discountIds)
    {
        $this->relations['conditions'] = DiscountCondition::select(['discount_id', 'type', 'condition'])
            ->whereIn('discount_id', $discountIds)
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получает все категории
     * @return $this
     */
    protected function fetchCategories()
    {
        if ($this->categories->isNotEmpty()) {
            return $this;
        }

        $categoryService = resolve(CategoryService::class);
        $this->categories = $categoryService->categories($categoryService->newQuery())->keyBy('id');
        return $this;
    }

    /**
     * @param $discountId
     * @return Collection
     */
    protected function getExceptOffersForDiscount($discountId)
    {
        return $this->relations['offers']->has($discountId)
            ? $this->relations['offers'][$discountId]->filter(function ($offer) {
                return $offer['except'];
            })->pluck('offer_id')
            : collect();
    }

    /**
     * @param $discountId
     * @return Collection
     */
    protected function getExceptBrandsForDiscount($discountId)
    {
        return $this->relations['brands']->has($discountId)
            ? $this->relations['brands'][$discountId]->filter(function ($brand) {
                return $brand['except'];
            })->pluck('brand_id')
            : collect();
    }

    /**
     * Существует ли хотя бы одна скидка с одним из типов скидки ($types)
     * @param array $types
     * @return bool
     */
    protected function existsAnyTypeInDiscounts(array $types): bool
    {
        return $this->discounts->groupBy('type')
            ->keys()
            ->intersect($types)
            ->isEmpty();
    }

    /**
     * Можно ли применить данную скидку (независимо от других скидок)
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkDiscount(Discount $discount): bool
    {
        return $this->checkPromo($discount)
            && $this->checkType($discount)
            && $this->checkCustomerRole($discount)
            && $this->checkSegment($discount);
    }

    /**
     * Если скидку можно получить только по промокоду, проверяем введен ли нужный промокод
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkPromo(Discount $discount): bool
    {
        return !$discount->promo_code_only || $this->appliedDiscounts->search($discount->id) !== false;
    }

    /**
     * Проверяет все необходимые условия по свойству "Тип скидки"
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkType(Discount $discount): bool
    {
        switch ($discount->type) {
            case Discount::DISCOUNT_TYPE_OFFER:
                return $this->checkOffers($discount);
            case Discount::DISCOUNT_TYPE_BUNDLE:
                return $this->checkBundles($discount);
            case Discount::DISCOUNT_TYPE_BRAND:
                return $this->checkBrands($discount);
            case Discount::DISCOUNT_TYPE_CATEGORY:
                return $this->checkCategories($discount);
            case Discount::DISCOUNT_TYPE_DELIVERY:
                return isset($this->filter['delivery']['price']);
            case Discount::DISCOUNT_TYPE_CART_TOTAL:
                return $this->filter['offers']->isNotEmpty();
            default:
                return false;
        }
    }

    /**
     * Проверяет доступность применения скидки на офферы
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkOffers(Discount $discount): bool
    {
        return $discount->type === Discount::DISCOUNT_TYPE_OFFER
            && $this->relations['offers']->has($discount->id)
            && $this->relations['offers'][$discount->id]->filter(function ($offers) {
                return !$offers['except'];
            })->isNotEmpty();
    }

    /**
     * Проверяет доступность применения скидки на бандлы
     *
     * @param Discount $discount
     * @return bool
     * @todo
     */
    protected function checkBundles(Discount $discount): bool
    {
        return $discount->type === Discount::DISCOUNT_TYPE_BUNDLE && !empty($this->filter['bundles']);
    }

    /**
     * Проверяет доступность применения скидки на бренды
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkBrands(Discount $discount): bool
    {
        return $discount->type === Discount::DISCOUNT_TYPE_BRAND
            && $this->relations['brands']->has($discount->id)
            && $this->relations['brands'][$discount->id]->filter(function ($brand) {
                return !$brand['except'];
            })->isNotEmpty();
    }

    /**
     * Проверяет доступность применения скидки на категории
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkCategories(Discount $discount): bool
    {
        return $discount->type === Discount::DISCOUNT_TYPE_CATEGORY
            && $this->relations['categories']->has($discount->id)
            && $this->relations['categories'][$discount->id]->isNotEmpty();
    }

    /**
     * @param Discount $discount
     * @return bool
     */
    protected function checkCustomerRole(Discount $discount): bool
    {
        return !$this->relations['roles']->has($discount->id) ||
            (
                isset($this->filter['customer']['roles'])
                && $this->relations['roles'][$discount->id]->intersect($this->filter['customer']['roles'])->isNotEmpty()
            );
    }

    /**
     * @param Discount $discount
     * @return bool
     */
    protected function checkSegment(Discount $discount): bool
    {
        // Если отсутствуют условия скидки на сегмент
        if (!$this->relations['segments']->has($discount->id)) {
            return true;
        }

        return isset($this->filter['customer']['segment'])
            && $this->relations['segments'][$discount->id]->search($this->filter['customer']['segment']) !== false;
    }

    /**
     * Проверяет доступность применения скидки на все соответствующие условия
     *
     * @param Collection $conditions
     * @return bool
     * @todo
     */
    protected function checkConditions(Collection $conditions): bool
    {
        /** @var DiscountCondition $condition */
        foreach ($conditions as $condition) {
            switch ($condition->type) {
                /** Скидка на первый заказ */
                case DiscountCondition::FIRST_ORDER:
                    $r = $this->getCountOrders() === 0;
                    break;
                /** Скидка на заказ от заданной суммы */
                case DiscountCondition::MIN_PRICE_ORDER:
                    $r = $this->getCostOrders() >= $condition->getMinPrice();
                    break;
                /** Скидка на заказ от заданной суммы на один из брендов */
                case DiscountCondition::MIN_PRICE_BRAND:
                    $r = $this->getMaxTotalPriceForBrands($condition->getBrands()) >= $condition->getMinPrice();
                    break;
                /** Скидка на заказ от заданной суммы на одну из категорий */
                case DiscountCondition::MIN_PRICE_CATEGORY:
                    $r = $this->getMaxTotalPriceForCategories($condition->getCategories()) >= $condition->getMinPrice();
                    break;
                /** Скидка на заказ определенного количества товара */
                case DiscountCondition::EVERY_UNIT_PRODUCT:
                    $r = $this->checkEveryUnitProduct($condition->getOffer(), $condition->getCount());
                    break;
                /** Скидка на один из методов доставки */
                case DiscountCondition::DELIVERY_METHOD:
                    $r = $this->checkDeliveryMethod($condition->getDeliveryMethods());
                    break;
                /** Скидка на один из методов оплаты */
                case DiscountCondition::PAY_METHOD:
                    $r = $this->checkPayMethod($condition->getPaymentMethods());
                    break;
                /** Скидка при заказе в один из регионов */
                case DiscountCondition::REGION:
                    $r = $this->checkRegion($condition->getRegions());
                    break;
                case DiscountCondition::CUSTOMER:
                    $r = in_array($this->getCustomerId(), $condition->getCustomerIds());
                    break;
                /** Скидка на каждый N-й заказ */
                case DiscountCondition::ORDER_SEQUENCE_NUMBER:
                    $countOrders = $this->getCountOrders();
                    $r = isset($countOrders) && (($countOrders + 1) % $condition->getOrderSequenceNumber() === 0);
                    break;
                case DiscountCondition::BUNDLE:
                    $r = true; // todo
                    break;
                case DiscountCondition::DISCOUNT_SYNERGY:
                    continue(2); # Проверяет отдельно на этапе применения скидок
                default:
                    return false;
            }

            if (!$r) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return int|null
     */
    public function getCustomerId()
    {
        return $this->filter['customer']['id'] ?? null;
    }

    /**
     * @return int|null
     */
    public function getCountOrders()
    {
        return $this->filter['customer']['orders']['count'] ?? null;
    }

    /**
     * Заказ от определенной суммы
     * @return int
     */
    public function getPriceOrders()
    {
        return $this->filter['offers']->map(function ($offer) {
            return $offer['price'] * $offer['qty'];
        })->sum();
    }

    /**
     * Сумма заказа без учета скидки
     * @return int
     */
    public function getCostOrders()
    {
        return $this->filter['offers']->map(function ($offer) {
            return ($offer['cost'] ?? $offer['price']) * $offer['qty'];
        })->sum();
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
            $sum = $this->filter['offers']->filter(function ($offer) use ($brandId) {
                return $offer['brand_id'] === $brandId;
            })->map(function ($offer) {
                return $offer['price'] * $offer['qty'];
            })->sum();
            $max = max($sum, $max);
        }

        return $max;
    }

    /**
     * @param array $categories
     * @return int
     */
    public function getMaxTotalPriceForCategories($categories)
    {
        $max = 0;
        foreach ($categories as $categoryId) {
            $sum = $this->filter['offers']->filter(function ($offer) use ($categoryId) {
                return $this->categories->has($categoryId)
                    && $this->categories->has($offer['category_id'])
                    && $this->categories[$categoryId]->isSelfOrAncestorOf($this->categories[$offer['category_id']]);
            })->map(function ($offer) {
                return $offer['price'] * $offer['qty'];
            })->sum();
            $max = max($sum, $max);
        }

        return $max;
    }

    /**
     * Количество единиц одного оффера
     * @param $offerId
     * @param $count
     * @return bool
     */
    public function checkEveryUnitProduct($offerId, $count)
    {
        return $this->filter['offers']->has($offerId) && $this->filter['offers'][$offerId]['qty'] >= $count;
    }

    /**
     * Способ доставки
     * @param $deliveryMethods
     * @return bool
     */
    public function checkDeliveryMethod($deliveryMethods)
    {
        return isset($this->filter['delivery']['method']) && in_array($this->filter['delivery']['method'], $deliveryMethods);
    }

    /**
     * Способ оплаты
     * @param $payments
     * @return bool
     */
    public function checkPayMethod($payments)
    {
        return isset($this->filter['payment']['method']) && in_array($this->filter['payment']['method'], $payments);
    }

    /**
     * Регион доставки
     * @param $regions
     * @return bool
     */
    public function checkRegion($regions)
    {
        return isset($this->filter['delivery']['region']) && in_array($this->filter['delivery']['region'], $regions);
    }
}
