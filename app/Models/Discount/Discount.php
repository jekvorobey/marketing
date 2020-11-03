<?php

namespace App\Models\Discount;

use Carbon\Carbon;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Eloquent;
use DB;
use Greensight\CommonMsa\Dto\UserDto;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use MerchantManagement\Services\OperatorService\OperatorService;
use Pim\Services\SearchService\SearchService;

/**
 * Класс-модель для сущности "Скидка"
 * App\Models\Discount
 *
 * @property int $merchant_id
 * @property int $user_id
 * @property int $type
 * @property string|null $name
 * @property int $value_type
 * @property int $value
 * @property int $status
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property boolean $promo_code_only
 * @mixin Eloquent
 *
 * @property-read Collection|DiscountOffer[] $offers
 * @property-read Collection|BundleItem[] $bundleItems
 * @property-read Collection|DiscountBrand[] $brands
 * @property-read Collection|DiscountCategory[] $categories
 * @property-read Collection|DiscountUserRole[] $roles
 * @property-read Collection|DiscountUserRole[] $segments
 * @property-read Collection|DiscountCondition[] $conditions
 * @property-read Collection|DiscountPublicEvent[] $publicEvents
 */
class Discount extends AbstractModel
{
    /**
     * Статус скидки
     */
    /** Создана */
    const STATUS_CREATED = 1;
    /** Отправлена на согласование */
    const STATUS_SENT = 2;
    /** На согласовании */
    const STATUS_ON_CHECKING = 3;
    /** Активна */
    const STATUS_ACTIVE = 4;
    /** Отклонена */
    const STATUS_REJECTED = 5;
    /** Приостановлена */
    const STATUS_PAUSED = 6;
    /** Завершена */
    const STATUS_EXPIRED = 7;

    /**
     * Тип скидки (назначается на)
     */
    /** Скидка на оффер */
    const DISCOUNT_TYPE_OFFER = 1;
    /** Скидка на бандл из товаров */
    const DISCOUNT_TYPE_BUNDLE_OFFER = 21;
    /** Скидка на бандл из мастер-классов */
    const DISCOUNT_TYPE_BUNDLE_MASTERCLASS = 22;
    /** Скидка на бренд */
    const DISCOUNT_TYPE_BRAND = 3;
    /** Скидка на категорию */
    const DISCOUNT_TYPE_CATEGORY = 4;
    /** Скидка на доставку */
    const DISCOUNT_TYPE_DELIVERY = 5;
    /** Скидка на сумму корзины */
    const DISCOUNT_TYPE_CART_TOTAL = 6;
    /** Скидка на все офферы */
    const DISCOUNT_TYPE_ANY_OFFER = 7;
    /** Скидка на все бандлы */
    const DISCOUNT_TYPE_ANY_BUNDLE = 8;
    /** Скидка на все бренды */
    const DISCOUNT_TYPE_ANY_BRAND = 9;
    /** Скидка на все категории */
    const DISCOUNT_TYPE_ANY_CATEGORY = 10;
    /** Скидка на мастер-класс по ID типа билета */
    const DISCOUNT_TYPE_MASTERCLASS = 11;
    /** Скидка на все мастер-классы */
    const DISCOUNT_TYPE_ANY_MASTERCLASS = 12;

    /**
     * Тип скидки для вывода в корзину/чекаут
     */
    /** Скидка "На товар" */
    const EXT_TYPE_OFFER = 1;
    /** Скидка "На доставку" */
    const EXT_TYPE_DELIVERY = 2;
    /** Скидка "На корзину" */
    const EXT_TYPE_CART = 3;
    /** Скидка "Для Вас" */
    const EXT_TYPE_PERSONAL = 4;
    /** Скидка "По промокоду" */
    const EXT_TYPE_PROMO = 5;

    /** Спонсор скидки */
    const DISCOUNT_MERCHANT_SPONSOR = 1;
    const DISCOUNT_ADMIN_SPONSOR = 2;

    /** Тип значения – Проценты */
    const DISCOUNT_VALUE_TYPE_PERCENT = 1;
    /** Тип значения – Рубли */
    const DISCOUNT_VALUE_TYPE_RUB = 2;

    const DISCOUNT_OFFER_RELATION = 1;
    const DISCOUNT_BRAND_RELATION = 2;
    const DISCOUNT_CATEGORY_RELATION = 3;
    const DISCOUNT_SEGMENT_RELATION = 4;
    const DISCOUNT_USER_ROLE_RELATION = 5;
    const DISCOUNT_CONDITION_RELATION = 6;
    const DISCOUNT_BUNDLE_RELATION = 7;
    const DISCOUNT_PUBLIC_EVENT_RELATION = 8;

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'user_id',
        'merchant_id',
        'type',
        'name',
        'value_type',
        'value',
        'status',
        'start_date',
        'end_date',
        'promo_code_only',
    ];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /**
     * @var array
     */
    protected $casts = [
        'promo_code_only' => 'bool',
    ];

    /**
     * Доступные типы скидок
     * @return array
     */
    public static function availableTypes()
    {
        return [
            self::DISCOUNT_TYPE_OFFER,
            self::DISCOUNT_TYPE_ANY_OFFER,
            self::DISCOUNT_TYPE_BUNDLE_OFFER,
            self::DISCOUNT_TYPE_BUNDLE_MASTERCLASS,
            self::DISCOUNT_TYPE_ANY_BUNDLE,
            self::DISCOUNT_TYPE_BRAND,
            self::DISCOUNT_TYPE_ANY_BRAND,
            self::DISCOUNT_TYPE_CATEGORY,
            self::DISCOUNT_TYPE_ANY_CATEGORY,
            self::DISCOUNT_TYPE_DELIVERY,
            self::DISCOUNT_TYPE_CART_TOTAL,
            self::DISCOUNT_TYPE_MASTERCLASS,
            self::DISCOUNT_TYPE_ANY_MASTERCLASS
        ];
    }

    /**
     * Доступные статусы скидок
     * @return array
     */
    public static function availableStatuses()
    {
        return [
            self::STATUS_CREATED,
            self::STATUS_SENT,
            self::STATUS_ON_CHECKING,
            self::STATUS_ACTIVE,
            self::STATUS_REJECTED,
            self::STATUS_PAUSED,
            self::STATUS_EXPIRED,
        ];
    }

    /**
     * @return array
     */
    public static function availableRelations()
    {
        return [
            Discount::DISCOUNT_OFFER_RELATION,
            Discount::DISCOUNT_BRAND_RELATION,
            Discount::DISCOUNT_CATEGORY_RELATION,
            Discount::DISCOUNT_SEGMENT_RELATION,
            Discount::DISCOUNT_USER_ROLE_RELATION,
            Discount::DISCOUNT_CONDITION_RELATION,
            Discount::DISCOUNT_BUNDLE_RELATION,
            Discount::DISCOUNT_PUBLIC_EVENT_RELATION,
        ];
    }

    /**
     * @param int $discountType
     * @param array $discountConditions
     * @param bool $isPromo
     * @return int|null
     */
    public static function getExternalType(int $discountType, array $discountConditions, bool $isPromo)
    {
        if ($isPromo) {
            return self::EXT_TYPE_PROMO;
        }

        foreach ($discountConditions as $discountCondition) {
            switch ($discountCondition['type']) {
                case DiscountCondition::FIRST_ORDER:
                case DiscountCondition::MIN_PRICE_ORDER:
                case DiscountCondition::MIN_PRICE_BRAND:
                case DiscountCondition::MIN_PRICE_CATEGORY:
                case DiscountCondition::EVERY_UNIT_PRODUCT:
                case DiscountCondition::DELIVERY_METHOD:
                case DiscountCondition::PAY_METHOD:
                case DiscountCondition::REGION:
                case DiscountCondition::CUSTOMER:
                case DiscountCondition::ORDER_SEQUENCE_NUMBER:
                    return self::EXT_TYPE_PERSONAL;
            }
        }

        switch ($discountType) {
            case self::DISCOUNT_TYPE_OFFER:
            case self::DISCOUNT_TYPE_ANY_OFFER:
            case self::DISCOUNT_TYPE_BUNDLE_OFFER:
            case self::DISCOUNT_TYPE_BUNDLE_MASTERCLASS:
            case self::DISCOUNT_TYPE_ANY_BUNDLE:
            case self::DISCOUNT_TYPE_BRAND:
            case self::DISCOUNT_TYPE_ANY_BRAND:
            case self::DISCOUNT_TYPE_CATEGORY:
            case self::DISCOUNT_TYPE_ANY_CATEGORY:
            case self::DISCOUNT_TYPE_MASTERCLASS:
            case self::DISCOUNT_TYPE_ANY_MASTERCLASS:
                return self::EXT_TYPE_OFFER;
            case self::DISCOUNT_TYPE_DELIVERY:
                return self::EXT_TYPE_DELIVERY;
            case self::DISCOUNT_TYPE_CART_TOTAL:
                return self::EXT_TYPE_CART;
        }

        return null;
    }

    /**
     * @return array
     */
    public function getMappingRelations()
    {
        return [
            Discount::DISCOUNT_OFFER_RELATION => ['class' => DiscountOffer::class, 'items' => $this->offers],
            Discount::DISCOUNT_BRAND_RELATION => ['class' => DiscountBrand::class, 'items' => $this->brands],
            Discount::DISCOUNT_CATEGORY_RELATION => ['class' => DiscountCategory::class, 'items' => $this->categories],
            Discount::DISCOUNT_SEGMENT_RELATION => ['class' => DiscountSegment::class, 'items' => $this->segments],
            Discount::DISCOUNT_USER_ROLE_RELATION => ['class' => DiscountUserRole::class, 'items' => $this->roles],
            Discount::DISCOUNT_CONDITION_RELATION => ['class' => DiscountCondition::class, 'items' => $this->conditions],
            Discount::DISCOUNT_BUNDLE_RELATION => ['class' => BundleItem::class, 'items' => $this->bundleItems],
            Discount::DISCOUNT_PUBLIC_EVENT_RELATION => ['class' => DiscountPublicEvent::class, 'items' => $this->publicEvents],
        ];
    }

    /**
     * @return HasMany
     */
    public function offers()
    {
        return $this->hasMany(DiscountOffer::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function bundleItems()
    {
        return $this->hasMany(BundleItem::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function brands()
    {
        return $this->hasMany(DiscountBrand::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function categories()
    {
        return $this->hasMany(DiscountCategory::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function roles()
    {
        return $this->hasMany(DiscountUserRole::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function segments()
    {
        return $this->hasMany(DiscountSegment::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function conditions()
    {
        return $this->hasMany(DiscountCondition::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function publicEvents()
    {
        return $this->hasMany(DiscountPublicEvent::class, 'discount_id');
    }

    /**
     * @return bool
     */
    public function isExpired()
    {
        $now = Carbon::now();
        return (isset($this->start_date) && $now->lt($this->start_date))
            || (isset($this->end_date) && $now->gt($this->end_date));
    }

    /**
     * @param Builder $query
     * @param $roleId
     * @return Builder
     */
    public function scopeForRoleId(Builder $query, int $roleId): Builder
    {
        return $query->whereHas('roles', function (Builder $query) use ($roleId) {
            $query->where('role_id', $roleId);
        });
    }

    /**
     * Активные и доступные на заданную дату скидки
     *
     * @param Builder $query
     * @param Carbon|null $date
     * @return Builder
     */
    public function scopeActive(Builder $query, ?Carbon $date = null): Builder
    {
        $date = $date ?? Carbon::now();
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($query) use ($date) {
                $query->where('start_date', '<=', $date)->orWhereNull('start_date');
            })->where(function ($query) use ($date) {
                $query->where('end_date', '>=', $date)->orWhereNull('end_date');
            });
    }

    /**
     * Скидки, которые могут быть показаны (рассчитаны) в каталоге
     * @param Builder $query
     * @return Builder
     */
    public function scopeShowInCatalog(Builder $query): Builder
    {
        return $query
            ->active()
            ->where('promo_code_only', false)
            ->whereIn('type', [
                self::DISCOUNT_TYPE_OFFER,
                self::DISCOUNT_TYPE_ANY_OFFER,
                self::DISCOUNT_TYPE_BRAND,
                self::DISCOUNT_TYPE_ANY_BRAND,
                self::DISCOUNT_TYPE_CATEGORY,
                self::DISCOUNT_TYPE_ANY_CATEGORY,
                self::DISCOUNT_TYPE_MASTERCLASS,
                self::DISCOUNT_TYPE_ANY_MASTERCLASS
            ]);
    }

    /**
     * Сделать скидку совместимой с другой скидкой
     * @param Discount|int $other
     * @return bool
     */
    public function makeCompatible($other)
    {
        $otherId = is_int($other) ? $other : $other->id;
        if ($this->id === $otherId) {
            return false;
        }

        try {
            DB::beginTransaction();

            /** @var DiscountCondition[] $conditions */
            $conditions = DiscountCondition::query()
                ->whereIn('discount_id', [$this->id, $otherId])
                ->where('type', DiscountCondition::DISCOUNT_SYNERGY)
                ->get()
                ->keyBy('discount_id');

            $thisSynergy = collect($conditions[$this->id]['condition'][DiscountCondition::FIELD_SYNERGY] ?? [])
                ->push($otherId)
                ->values()
                ->unique()
                ->toArray();

            $otherSynergy = collect($conditions[$otherId]['condition'][DiscountCondition::FIELD_SYNERGY] ?? [])
                ->push($this->id)
                ->values()
                ->unique()
                ->toArray();

            if ($conditions->has($this->id) && $conditions->has($otherId)) {
                $conditions[$this->id]->condition = [DiscountCondition::FIELD_SYNERGY => $thisSynergy];
                $conditions[$this->id]->update();

                $conditions[$otherId]->condition = [DiscountCondition::FIELD_SYNERGY => $otherSynergy];
                $conditions[$otherId]->update();
            } elseif (!$conditions->has($this->id)) {
                DiscountCondition::create([
                    'discount_id' => $this->id,
                    'type' => DiscountCondition::DISCOUNT_SYNERGY,
                    'condition' => [DiscountCondition::FIELD_SYNERGY => $thisSynergy]
                ]);
            } else {
                DiscountCondition::create([
                    'discount_id' => $otherId,
                    'type' => DiscountCondition::DISCOUNT_SYNERGY,
                    'condition' => [DiscountCondition::FIELD_SYNERGY => $otherSynergy]
                ]);
            }

            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * Проверяет корректные ли данные хранятся в сущности Discount
     * (не проверяет корректность связанных сущностей)
     * @return bool
     */
    public function validate(): bool
    {
        return $this->value >= 1 &&
            in_array($this->type, [
                self::DISCOUNT_TYPE_OFFER,
                self::DISCOUNT_TYPE_ANY_OFFER,
                self::DISCOUNT_TYPE_BUNDLE_OFFER,
                self::DISCOUNT_TYPE_BUNDLE_MASTERCLASS,
                self::DISCOUNT_TYPE_ANY_BUNDLE,
                self::DISCOUNT_TYPE_BRAND,
                self::DISCOUNT_TYPE_ANY_BRAND,
                self::DISCOUNT_TYPE_CATEGORY,
                self::DISCOUNT_TYPE_ANY_CATEGORY,
                self::DISCOUNT_TYPE_DELIVERY,
                self::DISCOUNT_TYPE_CART_TOTAL,
                self::DISCOUNT_TYPE_MASTERCLASS,
                self::DISCOUNT_TYPE_ANY_MASTERCLASS
            ]) && in_array($this->value_type, [
                self::DISCOUNT_VALUE_TYPE_PERCENT,
                self::DISCOUNT_VALUE_TYPE_RUB
            ]) && (
                $this->value_type == self::DISCOUNT_VALUE_TYPE_RUB || $this->value <= 100
            ) && in_array($this->status, [
                self::STATUS_CREATED,
                self::STATUS_SENT,
                self::STATUS_ON_CHECKING,
                self::STATUS_REJECTED,
                self::STATUS_PAUSED,
                self::STATUS_EXPIRED
            ]) && (
                !isset($this->start_date)
                || !isset($this->end_date)
                || Carbon::parse($this->start_date)->lte(Carbon::parse($this->end_date))
            );
    }

    public static function boot()
    {
        parent::boot();

        self::saved(function (self $discount) {
            $discount->updatePimContents();

            $operatorService = app(OperatorService::class);
            $serviceNotificationService = app(ServiceNotificationService::class);

            /** @var UserService */
            $userService = app(UserService::class);

            /** @var CustomerService */
            $customerService = app(CustomerService::class);

            $operators = $operatorService->operators((new RestQuery)->setFilter('merchant_id', '=', $discount->merchant_id));

            [$type, $data] = (function () use ($discount) {
                switch ($discount->status) {
                    case static::STATUS_CREATED:
                        return ['marketingskidka_sozdana', []];
                    case static::STATUS_SENT:
                        return ['marketingskidka_otpravlena_na_soglasovanie', []];
                    case static::STATUS_ON_CHECKING:
                        return ['marketingskidka_na_soglasovanii', []];
                    case static::STATUS_ACTIVE:
                        return ['marketingskidka_aktivna', [
                            'NAME_DISCOUNT' => $discount->name
                        ]];
                    case static::STATUS_REJECTED:
                        return ['marketingskidka_otklonena', [
                            'NAME_DISCOUNT' => $discount->name
                        ]];
                    case static::STATUS_PAUSED:
                        return ['marketingskidka_priostanovlena', [
                            'NAME_DISCOUNT' => $discount->name
                        ]];
                    case static::STATUS_EXPIRED:
                        return ['marketingskidka_zavershena', [
                            'NAME_DISCOUNT' => $discount->name
                        ]];
                    default:
                        return ['', []];
                }
            })();

            foreach($operators as $operator) {
                $serviceNotificationService->send($operator->user_id, $type, $data);
            }

            if($discount->status == static::STATUS_CREATED) {
                $serviceNotificationService->sendToAdmin('aozskidkaskidka_sozdana');
            } else {
                $serviceNotificationService->sendToAdmin('aozskidkaskidka_izmenena');
            }

            if($discount->value != $discount->getOriginal('value') || $discount->wasRecentlyCreated) {
                $sentIds = [];

                $discount
                    ->roles()
                    ->get()
                    ->map(function (DiscountUserRole $discountUserRole) use ($userService) {
                        return $userService->users(
                            $userService->newQuery()
                                ->setFilter('role', $discountUserRole->role_id)
                        );
                    })
                    ->each(function ($role) use ($serviceNotificationService, $discount, &$sentIds) {
                        $role->each(function ($user) use ($serviceNotificationService, $discount, &$sentIds) {
                            $sentIds[] = $user->id;

                            if($discount->value_type == Discount::DISCOUNT_VALUE_TYPE_PERCENT) {
                                $type = '%';
                            } else {
                                $type = ' руб.';
                            }

                            $serviceNotificationService->send($user->id, 'sotrudnichestvouroven_personalnoy_skidki_izmenen', [
                                'LVL_DISCOUNT' => sprintf("%s%s", $discount->value, $type),
                                'CUSTOMER_NAME' => $user->first_name
                            ]);
                        });
                    });

                // $discount
                //     ->conditions()
                //     ->whereJsonLength('condition->customerIds', '>=', 1)
                //     ->get()
                //     ->map(function (DiscountCondition $discountCondition) {
                //         return $discountCondition->condition['customerIds'];
                //     })
                //     ->flatten()
                //     ->unique()
                //     ->map(function ($customer) use ($customerService) {
                //         return $customerService->customers(
                //             $customerService->newQuery()
                //                 ->setFilter('id', $customer)
                //         )->first();
                //     })
                //     ->filter()
                //     ->map(function ($user) use ($userService) {
                //         return $userService->users(
                //             $userService->newQuery()
                //                 ->setFilter('id', $user->user_id)
                //         )->first();
                //     })
                //     ->filter()
                //     ->filter(function (UserDto $userDto) {
                //         return array_key_exists(UserDto::SHOWCASE__REFERRAL_PARTNER, $userDto->roles);
                //     })
                //     ->filter(function (UserDto $userDto) use ($sentIds) {
                //         return !in_array($userDto->id, $sentIds);
                //     })
                //     ->each(function (UserDto $userDto) use ($serviceNotificationService, $discount) {
                //         if($discount->value_type == Discount::DISCOUNT_VALUE_TYPE_PERCENT) {
                //             $type = '%';
                //         } else {
                //             $type = ' руб.';
                //         }

                //         $serviceNotificationService->send($userDto->id, 'sotrudnichestvouroven_personalnoy_skidki_izmenen', [
                //             'LVL_DISCOUNT' => sprintf("%s%s", $discount->value, $type),
                //             'CUSTOMER_NAME' => $userDto->first_name
                //         ]);
                //     });
            }
        });

        self::deleting(function (self $discount) {
            $synergy = DiscountCondition::query()
                ->where('discount_id', $discount->id)
                ->where('type', DiscountCondition::DISCOUNT_SYNERGY)
                ->first();

            if ($synergy) {
                $synergy->delete();
            }

            $discount->updatePimContents();

            $serviceNotificationService = app(ServiceNotificationService::class);
            $serviceNotificationService->sendToAdmin('aozskidkaskidka_udalena');
        });
    }

    public function updatePimContents()
    {
        static $actionPerformed = false;

        if (!$actionPerformed) {

            /** @var SearchService $searchService */
            $searchService = resolve(SearchService::class);

            $reindexRelations = function (string $function, string $relationName, string $column, bool &$actionPerformed) use ($searchService) {

                /** @var Collection $oldRelations */
                $oldRelations = $this->{$relationName};

                $this->refresh();
                /** @var Collection $newRelations */
                $newRelations = $this->{$relationName};

                $relations = array_unique(array_merge($oldRelations->pluck($column)->all(), $newRelations->pluck($column)->all()));
                if (!empty($relations)) {
                    call_user_func([$searchService, $function], $relations);
                    $actionPerformed = true;
                }
            };

            switch ($this->type) {
                case self::DISCOUNT_TYPE_OFFER:
                    $reindexRelations('markProductsForIndexByOfferIds', 'offers', 'offer_id', $actionPerformed);
                    break;
                case self::DISCOUNT_TYPE_BRAND:
                    $reindexRelations('markProductsForIndexByBrandIds', 'brands', 'brand_id', $actionPerformed);
                    break;
                case self::DISCOUNT_TYPE_CATEGORY:
                    $reindexRelations('markProductsForIndexByCategoryIds', 'categories', 'category_id', $actionPerformed);
                    break;
                case self::DISCOUNT_TYPE_ANY_OFFER:
                case self::DISCOUNT_TYPE_ANY_BRAND:
                case self::DISCOUNT_TYPE_ANY_CATEGORY:
                    $searchService->markAllProductsForIndex();
                    $actionPerformed = true;
                    break;
                case self::DISCOUNT_TYPE_MASTERCLASS:
                    $reindexRelations('markPublicEventsForIndexByTicketTypeIds', 'publicEvents', 'ticket_type_id', $actionPerformed);
                    break;
                case self::DISCOUNT_TYPE_ANY_MASTERCLASS:
                    $searchService->markAllPublicEventsForIndex();
                    $actionPerformed = true;
                    break;
                default:
                    break;
            }

        }
    }
}
