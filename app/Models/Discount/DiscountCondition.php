<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Dto\RoleDto;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Hash;
use Greensight\CommonMsa\Dto\UserDto;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;

/**
 * Класс-модель для сущности "Условие возникновения скидки"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $discount_condition_group_id
 * @property int $type
 * @property array $condition
 * @property-read Discount $discount @deprecated
 * @property DiscountConditionGroup $conditionGroup
 */
class DiscountCondition extends AbstractModel
{
    use Hash;

    /**
     * Тип условия возникновения права на скидку
     */
    /** На первый заказ */
    public const FIRST_ORDER = 1;

    /** На заказ от определенной суммы */
    public const MIN_PRICE_ORDER = 2;

    /** На заказ от определенной суммы товаров заданного бренда */
    public const MIN_PRICE_BRAND = 3;

    /** На заказ от определенной суммы товаров заданной категории */
    public const MIN_PRICE_CATEGORY = 4;

    /** На количество единиц одного товара */
    public const EVERY_UNIT_PRODUCT = 5;

    /** На способ доставки */
    public const DELIVERY_METHOD = 6;

    /** На способ оплаты */
    public const PAY_METHOD = 7;

    /** Территория действия (регион с точки зрения адреса доставки заказа) */
    public const REGION = 8;

    /** Для определенных покупателей */
    public const CUSTOMER = 9;

    /** Порядковый номер заказа */
    public const ORDER_SEQUENCE_NUMBER = 10;

    /** Взаимодействия с другими маркетинговыми инструментами */
    public const DISCOUNT_SYNERGY = 11;

    /**
     * Скидка на определенные бандлы.
     * Не показывается в списке условий.
     */
    public const BUNDLE = 12;

    /** @var int - условие на количество разных товаров в корзине */
    public const DIFFERENT_PRODUCTS_COUNT  = 13;

    /** Товары определенного поставщика(ов) */
    public const MERCHANT = 14;

    /** Товары с определенными характеристиками */
    public const PROPERTY = 15;

    /** Свойства условий скидки (для поля condition) */
    public const FIELD_MIN_PRICE = 'minPrice';
    public const FIELD_BRANDS = 'brands';
    public const FIELD_CATEGORIES = 'categories';
    public const FIELD_OFFER = 'offer';
    public const FIELD_COUNT = 'count';
    public const FIELD_DELIVERY_METHODS = 'deliveryMethods';
    public const FIELD_PAYMENT_METHODS = 'paymentMethods';
    public const FIELD_REGIONS = 'regions';
    public const FIELD_ORDER_SEQUENCE_NUMBER = 'orderSequenceNumber';
    public const FIELD_BUNDLES = 'bundles';
    public const FIELD_CUSTOMER_IDS = 'customerIds';
    public const FIELD_SYNERGY = 'synergy';
    public const FIELD_MAX_VALUE_TYPE = 'maxValueType';
    public const FIELD_MAX_VALUE = 'maxValue';
    public const FIELD_ADDITIONAL_DISCOUNT = 'additionalDiscount';
    public const FIELD_MERCHANTS = 'merchants';
    public const FIELD_PROPERTY = 'property';
    public const FIELD_PROPERTY_VALUES = 'propertyValues';

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['discount_id', 'type', 'condition', 'discount_condition_group_id'];

    /** @var array */
    protected $casts = [
        'condition' => 'array',
    ];

    /** @var array */
    protected $fillable = self::FILLABLE;

    public function conditionGroup(): BelongsTo
    {
        return $this->belongsTo(DiscountConditionGroup::class, 'discount_condition_group_id');
    }

    public function getMinPrice(): ?float
    {
        return $this->condition[self::FIELD_MIN_PRICE] ?? null;
    }

    public function getBrands(): array
    {
        return $this->condition[self::FIELD_BRANDS] ?? [];
    }

    public function getCategories(): array
    {
        return $this->condition[self::FIELD_CATEGORIES] ?? [];
    }

    public function getOffer(): ?int
    {
        return $this->condition[self::FIELD_OFFER] ?? null;
    }

    public function getCount(): ?int
    {
        return $this->condition[self::FIELD_COUNT] ?? null;
    }

    public function getDeliveryMethods(): array
    {
        return $this->condition[self::FIELD_DELIVERY_METHODS] ?? [];
    }

    public function getPaymentMethods(): array
    {
        return $this->condition[self::FIELD_PAYMENT_METHODS] ?? [];
    }

    public function getRegions(): array
    {
        return $this->condition[self::FIELD_REGIONS] ?? [];
    }

    public function getOrderSequenceNumber(): ?int
    {
        return $this->condition[self::FIELD_ORDER_SEQUENCE_NUMBER] ?? null;
    }

    public function getBundles(): array
    {
        return $this->condition[self::FIELD_BUNDLES] ?? [];
    }

    public function getCustomerIds(): array
    {
        return $this->condition[self::FIELD_CUSTOMER_IDS] ?? [];
    }

    public function getSynergy(): array
    {
        return $this->condition[self::FIELD_SYNERGY] ?? [];
    }

    public function getMaxValueType(): ?int
    {
        return $this->condition[self::FIELD_MAX_VALUE_TYPE] ?? null;
    }

    public function getMaxValue(): ?int
    {
        return $this->condition[self::FIELD_MAX_VALUE] ?? null;
    }

    public function getAdditionalDiscount(): ?float
    {
        return $this->condition[self::FIELD_ADDITIONAL_DISCOUNT] ?? null;
    }

    public function getMerchants(): array
    {
        return $this->condition[self::FIELD_MERCHANTS] ?? [];
    }

    public function getProperty(): ?int
    {
        return $this->condition[self::FIELD_PROPERTY] ?? null;
    }

    public function getPropertyValues(): array
    {
        return $this->condition[self::FIELD_PROPERTY_VALUES] ?? [];
    }

    public function setSynergy(array $value): void
    {
        $conditionArray = $this->condition ?? [];
        $conditionArray[self::FIELD_SYNERGY] = $value;
        $this->condition = $conditionArray;
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public static function boot()
    {
        parent::boot();

        self::created(function (DiscountCondition $item) {
            if ($item->type == self::CUSTOMER) {
                $serviceNotificationService = app(ServiceNotificationService::class);

                /** @var UserService $userService */
                $userService = app(UserService::class);

                /** @var CustomerService $customerService */
                $customerService = app(CustomerService::class);

                collect($item->getCustomerIds())
                    ->unique()
                    ->map(function ($customer) use ($customerService) {
                        return $customerService->customers(
                            $customerService->newQuery()
                                ->setFilter('id', $customer)
                        )->first();
                    })
                    ->filter()
                    ->map(function ($user) use ($userService) {
                        return $userService->users(
                            $userService->newQuery()
                                ->setFilter('id', $user->user_id)
                        )->first();
                    })
                    ->filter()
                    ->filter(function (UserDto $userDto) {
                        return array_key_exists(RoleDto::ROLE_SHOWCASE_REFERRAL_PARTNER, $userDto->roles);
                    })
                    ->each(function (UserDto $userDto) use ($serviceNotificationService, $item) {
                        if ($item->discount->value_type == Discount::DISCOUNT_VALUE_TYPE_PERCENT) {
                            $type = '%';
                        } else {
                            $type = ' руб.';
                        }

                        $serviceNotificationService->send($userDto->id, 'sotrudnichestvouroven_personalnoy_skidki_izmenen', [
                            'LVL_DISCOUNT' => sprintf('%s%s', $item->discount->value, $type),
                            'CUSTOMER_NAME' => $userDto->first_name,
                        ]);
                    });
            }

            if ($item->type !== self::DISCOUNT_SYNERGY || empty($item->condition[self::FIELD_SYNERGY] ?? [])) {
                return;
            }

            $discountIds = $item->condition[self::FIELD_SYNERGY];
            $conditions = DiscountCondition::query()
                ->where('type', self::DISCOUNT_SYNERGY)
                ->whereIn('discount_id', $discountIds)
                ->get()
                ->keyBy('discount_id');

            /** @var DiscountCondition $condition */
            foreach ($conditions as $condition) {
                $synergy = collect($condition->condition[DiscountCondition::FIELD_SYNERGY] ?? [])
                    ->push($item->discount_id)
                    ->unique()
                    ->values()
                    ->toArray();

                $condition->setSynergy($synergy);
                $condition->save();
            }

            foreach ($discountIds as $discountId) {
                if ($conditions->has($discountId)) {
                    continue;
                }

                $condition = new DiscountCondition();
                $condition->type = self::DISCOUNT_SYNERGY;
                $condition->setSynergy([$item->discount_id]);
                $condition->discount_id = $discountId;
                $condition->save();
            }
        });


        self::deleted(function (DiscountCondition $item)
        {
            if ($item->type !== self::DISCOUNT_SYNERGY) {
                return;
            }

            $conditions = DiscountCondition::query()
                ->where('type', self::DISCOUNT_SYNERGY)
                ->whereJsonContains('condition->synergy', $item->discount_id)
                ->orWhere(function ($builder) use ($item) {
                    return $builder->whereJsonContains('condition->synergy', "{$item->discount_id}");
                })
                ->get();

            /** @var DiscountCondition $condition */
            foreach ($conditions as $condition) {
                $synergy = $condition->condition[DiscountCondition::FIELD_SYNERGY];

                if (($key = array_search($item->discount_id, $synergy)) !== false) {
                    unset($synergy[$key]);
                    $synergy = array_values($synergy);
                    if (empty($synergy)) {
                        $condition->delete();
                    } else {
                        $condition->condition = array_merge($condition->condition, [DiscountCondition::FIELD_SYNERGY => $synergy]);
                        $condition->save();
                    }
                }
            }
        });
    }
}
