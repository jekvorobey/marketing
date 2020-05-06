<?php

namespace App\Services\Price;

use App\Models\Bonus\Bonus;
use App\Models\Discount\Discount;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCategory;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountOffer;
use App\Models\Discount\DiscountSegment;
use App\Models\Discount\DiscountUserRole;
use App\Models\Price\Price;
use App\Models\PromoCode\PromoCode;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Oms\Services\OrderService\OrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Pim\Dto\CategoryDto;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\CategoryService\CategoryService;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;

/**
 * Класс для расчета скидок (цен) для отображения в чекауте
 * Class CheckoutPriceCalculator
 * @package App\Core\Discount
 */
class CheckoutPriceCalculator
{
    /** @var int Самая низкая возможная цена (1 рубль) */
    const LOWEST_POSSIBLE_PRICE = 1;

    /** @var int Цена для бесплатной доставки */
    const FREE_DELIVERY_PRICE = 0;

    /** @var int Максимально возомжная скидка в процентах */
    const HIGHEST_POSSIBLE_PRICE_PERCENT = 100;

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
     * Список промокодов
     * @var Collection
     */
    protected $promoCodes;

    /**
     * Список примененных промокодов
     * @var Collection
     */
    protected $appliedPromoCodes;

    /**
     * Список категорий
     * @var CategoryDto[]|Collection
     */
    protected $categories;

    /**
     * Применить бесплатную доставку
     * @var bool
     */
    protected $freeDelivery = false;

    /**
     * Список возможных бонусов
     * @var Collection
     */
    protected $bonuses;

    /**
     * @var Collection
     */
    protected $appliedBonuses;

    /**
     * Количество бонусов для каждого оффера:
     * [offer_id => ['id' => bonus_id], ...]
     * @var Collection
     */
    protected $offersByBonuses;

    /**
     * Офферы со скидками в формате:
     * [offer_id => [['id' => discount_id, 'value' => value, 'value_type' => value_type], ...]]
     * @var Collection
     */
    protected $offersByDiscounts;

    /**
     * Если по какой-то скидке есть ограничение на максимальный итоговый размер скидки по офферу
     * @var array  [discount_id => ['value' => value, 'value_type' => value_type], ...]
     */
    protected $maxValueByDiscount = [];

    /**
     * DiscountCalculator constructor.
     * @param Collection $params
     * Формат:
     *  {
     *      'customer': ['id' => int],
     *      'offers': [['id' => int, 'qty' => int|null], ...]]
     *      'promoCode': string|null
     *      'deliveries': [['method' => int, 'price' => int, 'region' => int, 'selected' => bool], ...]
     *      'payment': ['method' => int]
     *  }
     */
    public function __construct(Collection $params)
    {
        $this->filter = [];
        $this->filter['bundles'] = []; // todo
        $this->filter['offers'] = $params['offers'] ?? collect();
        $this->filter['promoCode'] = $params['promoCode'] ?? null;
        $this->filter['customer'] = [
            'id' => isset($params['customer']['id']) ? intval($params['customer']['id']) : null,
            'roles' => $params['customer']['roles'] ?? [],
            'segment' => isset($params['customer']['segment']) ? intval($params['customer']['segment']) : null,
        ];
        $this->filter['payment'] = [
            'method' => isset($params['payment']['method']) ? intval($params['payment']['method']) : null
        ];

        /** Все возможные типы доставки */
        $this->filter['deliveries'] = collect();
        /** Выбранный тип доставки */
        $this->filter['selectedDelivery'] = null;
        /** Текущий тип доставки */
        $this->filter['delivery'] = null;
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

                if ($this->filter['deliveries'][$id]['selected']) {
                    $this->filter['selectedDelivery'] = $this->filter['deliveries'][$id];
                    $this->filter['delivery'] = $this->filter['deliveries'][$id];
                }
            }
        }

        /** Доставки, для которых необходимо посчитать только возможную скидку */
        $this->filter['notSelectedDeliveries'] = $this->filter['deliveries']->filter(function ($delivery) {
            return !$delivery['selected'];
        });

        $this->promoCodes = collect();
        $this->appliedPromoCodes = collect();
        $this->discounts = collect();
        $this->possibleDiscounts = collect();
        $this->bonuses = collect();
        $this->relations = collect();
        $this->categories = collect();
        $this->appliedDiscounts = collect();
        $this->appliedBonuses = collect();
        $this->offersByDiscounts = collect();
        $this->offersByBonuses = collect();
        $this->loadData();
    }

    /**
     * Возвращает данные о примененных скидках
     *
     * @return array
     */
    public function calculate()
    {
        $calculator = $this->getActivePromoCodes()
            ->filterPromoCodes()
            ->getActiveDiscounts()
            ->fetchData();

        /**
         * Считаются только возможные скидки.
         * Берем все доставки, для которых необходимо посчитать только возможную скидку,
         * по очереди применяем скидки (откатывая предыдущие изменяния, т.к. нельзя выбрать сразу две доставки),
         */
        foreach ($this->filter['notSelectedDeliveries'] as $delivery) {
            $deliveryId = $delivery['id'];
            $this->filter['delivery'] = $delivery;
            $calculator->filter()->sort()->apply();
            $deliveryWithDiscount = $this->filter['deliveries'][$deliveryId];
            $this->rollback();
            $this->filter['deliveries'][$deliveryId] = $deliveryWithDiscount;
        }

        /** Считаются окончательные скидки + бонусы */
        $this->filter['delivery'] = $this->filter['selectedDelivery'];
        $calculator->filter()
            ->sort()
            ->apply()
            ->getActiveBonuses()
            ->applyBonuses();

        return [
            'promoCodes' => $this->appliedPromoCodes->values(),
            'discounts' => $this->getExternalDiscountFormat(),
            'bonuses' => $this->appliedBonuses->values(),
            'offers' => $this->getFormatOffers(),
            'deliveries' => $this->filter['deliveries']->values(),
        ];
    }

    /**
     * @return array
     */
    public function getFormatOffers()
    {
        return $this->filter['offers']->map(function ($offer, $offerId) {
            $bonuses = $this->offersByBonuses[$offerId] ?? collect();
            return [
                'offer_id' => $offerId,
                'price' => $offer['price'],
                'qty' => floatval($offer['qty']),
                'cost' => $offer['cost'] ?? $offer['price'],
                'discount' => $this->offersByDiscounts->has($offerId)
                    ? $this->offersByDiscounts[$offerId]->values()->sum('change')
                    : null,
                'discounts' => $this->offersByDiscounts->has($offerId)
                    ? $this->offersByDiscounts[$offerId]->values()->toArray()
                    : null,
                'bonus' => $bonuses->reduce(function ($carry, $bonus) use ($offer) {
                    return $carry + $bonus['bonus'] * ($offer['qty'] ?? 1);
                }) ?? 0,
                'bonuses' => $bonuses,
            ];
        });
    }

    /**
     * @param $discounts
     * @return array
     */
    public function getExternalDiscountFormat()
    {
        $discounts = $this->discounts->filter(function ($discount) {
            return $this->appliedDiscounts->has($discount->id);
        })->keyBy('id');

        $items = [];
        foreach ($discounts as $discount) {
            $discountId = $discount->id;
            $conditions = $this->relations['conditions']->has($discountId)
                ? $this->relations['conditions'][$discountId]->toArray()
                : [];

            $extType = Discount::getExternalType($discount['type'], $conditions, $discount->promo_code_only);
            $items[] = [
                'id' => $discountId,
                'name' => $discount->name,
                'type' => $discount->type,
                'external_type' => $extType,
                'change' => $this->appliedDiscounts[$discountId]['change'],
                'merchant_id' => $discount->merchant_id,
                'visible_in_catalog' => $extType === Discount::EXT_TYPE_OFFER,
                'promo_code_only' => $discount->promo_code_only,
                'promo_code' => $discount->promo_code_only
                    ? $this->promoCodes->filter(function (PromoCode $promoCode) use ($discountId) {
                        return $promoCode->discount_id === $discountId;
                    })->first()->code
                    : null,
            ];
        }

        return $items;
    }

    /**
     * Загружает все необходимые данные
     * @return $this
     */
    protected function loadData()
    {
        $this->fetchCategories();
        /** @var Collection $offerIds */
        $offerIds = $this->filter['offers']->pluck('id');
        if ($offerIds->isNotEmpty()) {
            $this->hydrateOffer();
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
     * Заполняет информацию по офферам
     * @return $this
     */
    protected function hydrateOffer()
    {
        /** @var Collection $offerIds */
        $offerIds = $this->filter['offers']->pluck('id')->filter();

        if ($offerIds->isEmpty()) {
            return $this;
        }
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);

        $offersDto = $offerService->offers(
            (new RestQuery())->setFilter('id', $offerIds)->addFields(OfferDto::entity(), 'id', 'merchant_id')
        )->keyBy('id');
        $offers = collect();
        foreach ($this->filter['offers'] as $offer) {
            if (!isset($offer['id'])) {
                continue;
            }

            $offerId = (int)$offer['id'];
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
            ]));
        }
        $this->filter['offers'] = $offers;
        return $this;
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
                'merchant_id' => $offer['merchant_id'] ?? null,
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
                'merchant_id' => $offer['merchant_id'] ?? null,
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
        $this->discounts = Discount::select([
            'id',
            'type',
            'name',
            'merchant_id',
            'value',
            'value_type',
            'promo_code_only',
            'merchant_id'
        ])
            ->active()
            ->orderBy('promo_code_only')
            ->orderBy('type')
            ->get();

        return $this;
    }

    /**
     * Получить все активные промокоды
     *
     * @return $this
     */
    protected function getActivePromoCodes()
    {
        if (!$this->filter['promoCode']) {
            $this->promoCodes = collect();
            return $this;
        }

        $this->promoCodes = PromoCode::query()
            ->active()
            ->where('code', $this->filter['promoCode'])
            ->get();

        return $this;
    }

    /**
     * @return $this
     */
    protected function getActiveBonuses()
    {
        $this->bonuses = Bonus::query()
            ->active()
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
     * @return $this
     */
    protected function filter()
    {
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
     * @return $this
     */
    protected function filterPromoCodes()
    {
        $this->promoCodes = $this->promoCodes->filter(function (PromoCode $promoCode) {
            return $this->checkPromoCodeConditions($promoCode)
                && $this->checkPromoCodeCounter($promoCode);
        });

        return $this;
    }

    /**
     * Проверяет ограничения заданные в conditions
     * @param PromoCode $promoCode
     *
     * @return bool
     */
    protected function checkPromoCodeConditions(PromoCode $promoCode)
    {
        if (empty($promoCode->conditions)) {
            return true;
        }

        $roleIds = collect($promoCode->getRoleIds());
        if ($roleIds->isNotEmpty() && $roleIds->intersect($this->filter['customer']['roles'])->isEmpty()) {
            return false;
        }

        $customerIds = $promoCode->getCustomerIds();
        if (!empty($customerIds) && !in_array($this->filter['customer']['id'], $customerIds)) {
            return false;
        }

        $segmentIds = $promoCode->getSegmentIds();
        if (!empty($segmentIds) && !in_array($this->filter['customer']['segment'], $segmentIds)) {
            return false;
        }

        return true;
    }

    /**
     * Проверяет ограничение на количество применений одного промокода
     * @param PromoCode $promoCode
     *
     * @return bool
     */
    protected function checkPromoCodeCounter(PromoCode $promoCode)
    {
        if (!isset($promoCode->counter)) {
            return true;
        }

        $customerId = $this->getCustomerId();
        if (!$customerId) {
            return false;
        }

        /** @var OrderService $orderService */
        $orderService = resolve(OrderService::class);
        return $promoCode->counter > $orderService->orderPromoCodeCountByCustomer($promoCode->id, $customerId);
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
        $this->appliedPromoCodes = collect();
        $this->offersByDiscounts = collect();

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
     * Применяет промокоды и скидки
     *
     * @return $this
     */
    protected function apply()
    {
        return $this->applyPromoCodes()->applyDiscounts();
    }

    /**
     * Применяет промокоды
     * @return $this
     */
    protected function applyPromoCodes()
    {
        /** @var PromoCode $promoCode */
        foreach ($this->promoCodes as $promoCode) {
            if (!$this->isCompatiblePromoCode($promoCode)) {
                continue;
            }

            $change = null;
            $isApply = false;
            switch ($promoCode->type) {
                case PromoCode::TYPE_DISCOUNT:
                    $discountId = $promoCode->discount_id;
                    $isPossible = $this->possibleDiscounts->pluck('id')->search($discountId) !== false;
                    $discountsById = $this->discounts->keyBy('id');
                    if ($isPossible && $discountsById->has($discountId)) {
                        $change = $this->applyDiscount($discountsById[$discountId]);
                        $isApply = $change > 0;
                    }
                    break;
                case PromoCode::TYPE_DELIVERY:
                    // Мерчант не может изменять стоимость доставки
                    if ($promoCode->merchant_id) {
                        break;
                    }

                    $change = 0;
                    foreach ($this->filter['deliveries'] as $k => $delivery) {
                        $changeForDelivery = $this->changePrice(
                            $delivery,
                            self::HIGHEST_POSSIBLE_PRICE_PERCENT,
                            Discount::DISCOUNT_VALUE_TYPE_PERCENT,
                            true,
                            self::FREE_DELIVERY_PRICE
                        );

                        if ($changeForDelivery > 0) {
                            $this->filter['deliveries'][$k] = $delivery;
                            $isApply = $changeForDelivery > 0;
                            $change += $changeForDelivery;
                            $this->freeDelivery = true;
                        }
                    }
                    break;
                case PromoCode::TYPE_GIFT:
                    // todo
                    break;
                case PromoCode::TYPE_BONUS:
                    // todo
                    break;
            }

            if (!$isApply) {
                continue;
            }

            $this->appliedPromoCodes->put($promoCode->code, [
                'id' => $promoCode->id,
                'type' => $promoCode->type,
                'status' => $promoCode->status,
                'name' => $promoCode->name,
                'code' => $promoCode->code,
                'discount_id' => $promoCode->discount_id,
                'gift_id' => $promoCode->gift_id,
                'bonus_id' => $promoCode->bonus_id,
                'owner_id' => $promoCode->owner_id,
                'change' => $change
            ]);
        }

        return $this;
    }

    /**
     * Применяет скидки
     * @return $this
     */
    protected function applyDiscounts()
    {
        /** @var Discount $discount */
        foreach ($this->possibleDiscounts as $discount) {
            // Скидки по промокодам применяются отдельно
            if ($discount->promo_code_only) {
                continue;
            }

            $this->applyDiscount($discount);
        }

        return $this;
    }

    /**
     * @param Discount $discount
     *
     * @return int|bool – На сколько изменилась цена (false – скидку невозможно применить)
     */
    protected function applyDiscount(Discount $discount)
    {
        if (!$this->isCompatibleDiscount($discount)) {
            return false;
        }

        $change = false;
        switch ($discount->type) {
            case Discount::DISCOUNT_TYPE_OFFER:
            case Discount::DISCOUNT_TYPE_ANY_OFFER:
                # Скидка на офферы
                $offerIds = ($discount->type == Discount::DISCOUNT_TYPE_OFFER)
                    ? $this->relations['offers'][$discount->id]->pluck('offer_id')
                    : $this->filter['offers']->pluck('id');
                $change = $this->applyDiscountToOffer($discount, $offerIds);
                break;
            case Discount::DISCOUNT_TYPE_BUNDLE:
            case Discount::DISCOUNT_TYPE_ANY_BUNDLE:
                // todo
                break;
            case Discount::DISCOUNT_TYPE_BRAND:
            case Discount::DISCOUNT_TYPE_ANY_BRAND:
                # Скидка на бренды
                /** @var Collection $brandIds */
                $brandIds = ($discount->type == Discount::DISCOUNT_TYPE_BRAND)
                    ? $this->relations['brands'][$discount->id]->pluck('brand_id')
                    : $this->filter['brands'];

                # За исключением офферов
                $exceptOfferIds = $this->getExceptOffersForDiscount($discount->id);
                # Отбираем нужные офферы
                $offerIds = $this->filterForBrand($brandIds, $exceptOfferIds, $discount->merchant_id);
                $change = $this->applyDiscountToOffer($discount, $offerIds);
                break;
            case Discount::DISCOUNT_TYPE_CATEGORY:
            case Discount::DISCOUNT_TYPE_ANY_CATEGORY:
                # Скидка на категории
                /** @var Collection $categoryIds */
                $categoryIds = ($discount->type == Discount::DISCOUNT_TYPE_CATEGORY)
                    ? $this->relations['categories'][$discount->id]->pluck('category_id')
                    : $this->filter['categories'];
                # За исключением брендов
                $exceptBrandIds = $this->getExceptBrandsForDiscount($discount->id);
                # За исключением офферов
                $exceptOfferIds = $this->getExceptOffersForDiscount($discount->id);
                # Отбираем нужные офферы
                $offerIds = $this->filterForCategory($categoryIds, $exceptBrandIds, $exceptOfferIds, $discount->merchant_id);
                $change = $this->applyDiscountToOffer($discount, $offerIds);
                break;
            case Discount::DISCOUNT_TYPE_DELIVERY:
                // Если используется бесплатная дотсавка (например, по промокоду), то не использовать скидку
                if ($this->freeDelivery) {
                    break;
                }

                $deliveryId = $this->filter['delivery']['id'] ?? null;
                if ($this->filter['deliveries']->has($deliveryId)) {
                    $change = $this->changePrice(
                        $this->filter['delivery'],
                        $discount->value,
                        $discount->value_type,
                        true,
                        self::FREE_DELIVERY_PRICE
                    );

                    $this->filter['deliveries'][$deliveryId] = $this->filter['delivery'];
                }

                break;
            case Discount::DISCOUNT_TYPE_CART_TOTAL:
                $change = $this->applyDiscountToBasket($discount);
                break;
        }

        if ($change > 0) {
            $this->appliedDiscounts->put($discount->id, [
                'discountId' => $discount->id,
                'change' => $change,
                'conditions' => $this->relations['conditions']->has($discount->id)
                    ? $this->relations['conditions'][$discount->id]->pluck('type')
                    : [],
            ]);
        }

        return $change;
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
        $priceOrders = $this->getPriceOrders();
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
        while ($currentDiscountValue < $discountValue && $priceOrders !== 0) {
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
                $changeUp = $this->changePrice($offer, $valueUp, Discount::DISCOUNT_VALUE_TYPE_RUB, false);
                $changeDown = $this->changePrice($offer, $valueDown, Discount::DISCOUNT_VALUE_TYPE_RUB, false);
                if ($changeUp * $offer['qty'] <= $discountValue - $currentDiscountValue || $force) {
                    $change = $this->changePrice($offer, $valueUp, Discount::DISCOUNT_VALUE_TYPE_RUB);
                } elseif ($changeDown * $offer['qty'] <= $discountValue - $currentDiscountValue || $force) {
                    $change = $this->changePrice($offer, $valueDown, Discount::DISCOUNT_VALUE_TYPE_RUB);
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
     * @return $this
     */
    protected function applyBonuses()
    {
        $this->bonuses = $this->bonuses->filter(function (Bonus $bonus) {
            if (!$bonus->promo_code_only) {
                return true;
            }

            return $this->promoCodes->filter(function (PromoCode $promoCode) use ($bonus) {
                return $promoCode->bonus_id === $bonus->id;
            })->isNotEmpty();
        });

        /** @var Bonus $bonus */
        foreach ($this->bonuses as $bonus) {
            $bonusValue = 0;
            switch ($bonus->type) {
                case Bonus::TYPE_OFFER:
                    # Бонусы на офферы
                    $offerIds = $bonus->offers->pluck('offer_id');
                    $bonusValue = $this->applyBonusToOffer($bonus, $offerIds);
                    break;
                case Bonus::TYPE_BRAND:
                    # Бонусы на бренды
                    /** @var Collection $brandIds */
                    $brandIds = $bonus->brands->pluck('brand_id');
                    # За исключением офферов
                    $exceptOfferIds = $bonus->offers->pluck('offer_id');
                    # Отбираем нужные офферы
                    $offerIds = $this->filterForBrand($brandIds, $exceptOfferIds, null);
                    $bonusValue = $this->applyBonusToOffer($bonus, $offerIds);
                    break;
                case Bonus::TYPE_CATEGORY:
                    # Скидка на категории
                    /** @var Collection $categoryIds */
                    $categoryIds = $bonus->categories->pluck('category_id');
                    # За исключением брендов
                    $exceptBrandIds = $bonus->brands->pluck('brand_id');
                    # За исключением офферов
                    $exceptOfferIds = $bonus->offers->pluck('offer_id');
                    # Отбираем нужные офферы
                    $offerIds = $this->filterForCategory($categoryIds, $exceptBrandIds, $exceptOfferIds, null);
                    $bonusValue = $this->applyBonusToOffer($bonus, $offerIds);
                    break;
                case Bonus::TYPE_SERVICE:
                    // todo
                    break;
                case Bonus::TYPE_CART_TOTAL:
                    $price = $this->getPriceOrders();
                    $bonusValue = $this->priceToBonusValue($price, $bonus);
                    break;
            }

            if ($bonusValue > 0) {
                $this->appliedBonuses->put($bonus->id, [
                    'id' => $bonus->id,
                    'name' => $bonus->name,
                    'type' => $bonus->type,
                    'value' => $bonus->value,
                    'value_type' => $bonus->value_type,
                    'valid_period' => $bonus->valid_period,
                    'promo_code_only' => $bonus->promo_code_only,
                    'bonus' => $bonusValue,
                ]);
            }
        }

        return $this;
    }

    /**
     * @param       $price
     * @param Bonus $bonus
     *
     * @return int
     */
    protected function priceToBonusValue($price, Bonus $bonus)
    {
        switch ($bonus->value_type) {
            case Bonus::VALUE_TYPE_PERCENT:
                return round($price * $bonus->value / 100);
            case Bonus::VALUE_TYPE_RUB:
                return $bonus->value;
        }

        return 0;
    }

    /**
     * @param Bonus $bonus
     * @param       $offerIds
     *
     * @return bool|int
     */
    protected function applyBonusToOffer(Bonus $bonus, $offerIds)
    {
        $offerIds = $offerIds->filter(function ($offerId) use ($bonus) {
            return $this->filter['offers']->has($offerId);
        });

        if ($offerIds->isEmpty()) {
            return false;
        }

        $totalBonusValue = 0;
        foreach ($offerIds as $offerId) {
            $offer = &$this->filter['offers'][$offerId];
            $bonusValue = $this->priceToBonusValue($offer['price'], $bonus);

            if (!$this->offersByBonuses->has($offerId)) {
                $this->offersByBonuses->put($offerId, collect());
            }

            $this->offersByBonuses[$offerId]->push([
                'id' => $bonus->id,
                'bonus' => $bonusValue,
                'value' => $bonus->value,
                'value_type' => $bonus->value_type
            ]);
            $totalBonusValue += $bonusValue * $offer['qty'];
        }

        return $totalBonusValue;
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
     * @param Discount $discount
     * @param Collection $offerIds
     * @return bool Результат применения скидки
     */
    protected function applyDiscountToOffer($discount, Collection $offerIds)
    {
        $offerIds = $offerIds->filter(function ($offerId) use ($discount) {
            return $this->applicableToOffer($discount, $offerId);
        });

        if ($offerIds->isEmpty()) {
            return false;
        }

        $changed = 0;
        foreach ($offerIds as $offerId) {
            $offer = &$this->filter['offers'][$offerId];
            $lowestPossiblePrice = self::LOWEST_POSSIBLE_PRICE;

            // Если в условии на суммирование скидки было "не более x%", то переопределяем минимально возможную цену товара
            if (isset($this->maxValueByDiscount[$discount->id])) {

                // Получаем величину скидки, которая максимально возможна по условию
                $maxDiscountValue = $this->calculateDiscountByType(
                    $offer['cost'],
                    $this->maxValueByDiscount[$discount->id]['value'],
                    $this->maxValueByDiscount[$discount->id]['value_type']
                );

                // Чтобы не получить минимально возможную цену меньше 1р, выбираем наибольшее значение
                $lowestPossiblePrice = max($lowestPossiblePrice, $offer['cost'] - $maxDiscountValue);
            }

            $change = $this->changePrice($offer, $discount->value, $discount->value_type, true, $lowestPossiblePrice);
            if ($change <= 0) {
                continue;
            }

            if (!$this->offersByDiscounts->has($offerId)) {
                $this->offersByDiscounts->put($offerId, collect());
            }

            $this->offersByDiscounts[$offerId]->push([
                'id' => $discount->id,
                'change' => $change,
                'value' => $discount->value,
                'value_type' => $discount->value_type
            ]);
            $changed += $change * $offer['qty'];
        }

        return $changed;
    }

    /**
     * Возвращает размер скидки (без учета предыдущих скидок)
     *
     * @param      $item
     * @param      $value
     * @param int  $valueType
     * @param bool $apply нужно ли применять скидку
     * @param int  $lowestPossiblePrice Самая низкая возможная цена (по умолчанию = 1 рубль)
     *
     * @return int
     */
    protected function changePrice(
        &$item,
        $value,
        $valueType = Discount::DISCOUNT_VALUE_TYPE_RUB,
        $apply = true,
        int $lowestPossiblePrice = self::LOWEST_POSSIBLE_PRICE
    ) {
        if (!isset($item['price']) || $value <= 0) {
            return 0;
        }

        $currentDiscount = $item['discount'] ?? 0;
        $currentCost     = $item['cost'] ?? $item['price'];
        $discountValue = min($item['price'], $this->calculateDiscountByType($currentCost, $value, $valueType));

        /** Цена не может быть меньше $lowestPossiblePrice */
        if ($item['price'] - $discountValue < $lowestPossiblePrice) {
            $discountValue = $item['price'] - $lowestPossiblePrice;
        }

        if ($apply) {
            $item['discount'] = $currentDiscount + $discountValue;
            $item['price']    = $currentCost - $item['discount'];
            $item['cost']     = $currentCost;
        }

        return $discountValue;
    }

    protected function calculateDiscountByType($cost, $value, $valueType)
    {
        switch ($valueType) {
            case Discount::DISCOUNT_VALUE_TYPE_PERCENT:
                return round($cost * $value / 100);
            case Discount::DISCOUNT_VALUE_TYPE_RUB:
                return $value;
            default:
                return 0;
        }
    }

    /**
     * @param $brandIds
     * @param $exceptOfferIds
     * @param $merchantId
     * @return Collection
     */
    protected function filterForBrand($brandIds, $exceptOfferIds, $merchantId)
    {
        return $this->filter['offers']->filter(function ($offer) use ($brandIds, $exceptOfferIds, $merchantId) {
            return $brandIds->search($offer['brand_id']) !== false
                && $exceptOfferIds->search($offer['id']) === false
                && (!$merchantId || $offer['merchant_id'] == $merchantId);
        })->pluck('id');
    }

    /**
     * @param $categoryIds
     * @param $exceptBrandIds
     * @param $exceptOfferIds
     * @param $merchantId
     * @return Collection
     */
    protected function filterForCategory($categoryIds, $exceptBrandIds, $exceptOfferIds, $merchantId)
    {
        return $this->filter['offers']->filter(function ($offer) use ($categoryIds, $exceptBrandIds, $exceptOfferIds, $merchantId) {
            $offerCategory = $this->categories->has($offer['category_id'])
                ? $this->categories[$offer['category_id']]
                : null;

            return $offerCategory
                && $categoryIds->reduce(function ($carry, $categoryId) use ($offerCategory) {
                    return $carry ||
                        (
                            $this->categories->has($categoryId)
                            && $this->categories[$categoryId]->isSelfOrAncestorOf($offerCategory)
                        );
                })
                && $exceptBrandIds->search($offer['brand_id']) === false
                && $exceptOfferIds->search($offer['id']) === false
                && (!$merchantId || $offer['merchant_id'] == $merchantId);
        })->pluck('id');
    }

    /**
     * Совместимы ли скидки (даже если они не пересекаются)
     * @param Discount $discount
     * @return bool
     */
    protected function isCompatibleDiscount(Discount $discount)
    {
        return !$this->appliedDiscounts->has($discount->id);
    }

    /**
     * Совместимы ли промокоды
     * @param PromoCode $promoCode
     *
     * @return bool
     */
    protected function isCompatiblePromoCode(PromoCode $promoCode)
    {
        if ($this->appliedPromoCodes->isEmpty()) {
            return true;
        }

        $promoCodeIds = $promoCode->getCompatiblePromoCodes();
        if (empty($promoCodeIds)) {
            return false;
        }

        return $this->appliedPromoCodes->pluck('id')->diff($promoCodeIds)->isEmpty();
    }

    /**
     * Можно ли применить скидку к офферу
     * @param Discount $discount
     * @param $offerId
     * @return bool
     */
    protected function applicableToOffer($discount, $offerId)
    {
        if ($this->appliedDiscounts->isEmpty() || !$this->offersByDiscounts->has($offerId)) {
            return true;
        }

        if (!$this->relations['conditions']->has($discount->id)) {
            return false;
        }

        /** @var Collection $discountIdsForOffer */
        $discountIdsForOffer = $this->offersByDiscounts[$offerId]->pluck('id');
        /** @var DiscountCondition $condition */
        foreach ($this->relations['conditions'][$discount->id] as $condition) {
            if ($condition->type === DiscountCondition::DISCOUNT_SYNERGY) {
                $synergyDiscountIds = $condition->getSynergy();
                if ($discountIdsForOffer->intersect($synergyDiscountIds)->count() !== $discountIdsForOffer->count()) {
                    return false;
                }

                if ($condition->getMaxValueType()) {
                    $this->maxValueByDiscount[$discount->id] = [
                        'value_type' => $condition->getMaxValueType(),
                        'value' => $condition->getMaxValue(),
                    ];
                }

                return true;
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
        if (!$discount->promo_code_only) {
            return true;
        }

        return $this->promoCodes->filter(function (PromoCode $promoCode) use ($discount) {
               return $promoCode->discount_id === $discount->id;
        })->isNotEmpty();
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
            case Discount::DISCOUNT_TYPE_ANY_OFFER:
            case Discount::DISCOUNT_TYPE_ANY_BUNDLE:
            case Discount::DISCOUNT_TYPE_ANY_BRAND:
            case Discount::DISCOUNT_TYPE_ANY_CATEGORY:
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
                /** Скидка для определенных покупателей */
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
