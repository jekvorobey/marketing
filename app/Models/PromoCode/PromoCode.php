<?php

namespace App\Models\PromoCode;

use App\Models\Bonus\Bonus;
use App\Models\Discount\Discount;
use Carbon\Carbon;
use Greensight\CommonMsa\Models\AbstractModel;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

/**
 * Class PromoCode
 * @package App\Models\PromoCode
 *
 * @property int $creator_id
 * @property int|null $merchant_id
 * @property int|null $owner_id
 * @property string $name
 * @property string $code
 * @property int|null $counter
 * @property string|null $type_of_limit
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $status
 * @property int $type
 * @property int|null $discount_id
 * @property int|null $gift_id
 * @property int|null $bonus_id
 * @property array $conditions
 *
 * @property-read Collection|Discount[]|null $discounts
 * @property-read Bonus|null $bonus
 */
class PromoCode extends AbstractModel
{
    /**
     * Статус промокода
     */
    /** Создана */
    public const STATUS_CREATED = 1;

    /** Отправлена на согласование */
    public const STATUS_SENT = 2;

    /** На согласовании */
    public const STATUS_ON_CHECKING = 3;

    /** Активна */
    public const STATUS_ACTIVE = 4;

    /** Отклонена */
    public const STATUS_REJECTED = 5;

    /** Приостановлена */
    public const STATUS_PAUSED = 6;

    /** Завершена */
    public const STATUS_EXPIRED = 7;

    /** Тестовый */
    public const STATUS_TEST = 8;

    /**
     * Тип промокода (на что промокод)
     */
    /** Промокод на скидку */
    public const TYPE_DISCOUNT = 1;

    /** Промокод на бесплатную доставку */
    public const TYPE_DELIVERY = 2;

    /** Промокод на подарок */
    public const TYPE_GIFT = 3;

    /** Промокод на бонусы */
    public const TYPE_BONUS = 4;

    /**
     * Тип условия для применения промокода
     */
    /** Для определенного(ых) пользователя(ей) */
    public const CONDITION_TYPE_CUSTOMER_IDS = 'customers';

    /** Для определенного(ых) сегмента(ов) */
    public const CONDITION_TYPE_SEGMENT_IDS = 'segments';

    /** Для определенной(ых) роли(ей) */
    public const CONDITION_TYPE_ROLE_IDS = 'roles';

    /**
     * Тип ограничения количества использований
     */
    /** Для текущего пользователя */
    public const TYPE_OF_LIMIT_USER = 'user';

    /** Для всех пользователей */
    public const TYPE_OF_LIMIT_ALL = 'all';

    const SPONSOR_IBT = 'ibt';
    const SPONSOR_MERCHANT = 'merchant';

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = [
        'creator_id',
        'merchant_id',
        'owner_id',
        'name',
        'code',
        'counter',
        'type_of_limit',
        'start_date',
        'end_date',
        'status',
        'type',
        'gift_id',
        'bonus_id',
        'conditions',
    ];

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var array */
    protected $casts = [
        'conditions' => 'array',
    ];

    /**
     * Доступные статусы промокодов
     */
    public static function availableStatuses(): array
    {
        return [
            self::STATUS_CREATED,
            self::STATUS_SENT,
            self::STATUS_ON_CHECKING,
            self::STATUS_ACTIVE,
            self::STATUS_REJECTED,
            self::STATUS_PAUSED,
            self::STATUS_EXPIRED,
            self::STATUS_TEST,
        ];
    }

    /**
     * Типы промокодов
     */
    public static function availableTypes(): array
    {
        return [
            self::TYPE_DISCOUNT,
            self::TYPE_DELIVERY,
            self::TYPE_GIFT,
            self::TYPE_BONUS,
        ];
    }

    public static function availableTypesForMerchant(): array
    {
        return [
            self::TYPE_DISCOUNT,
            self::TYPE_GIFT,
            self::TYPE_BONUS,
        ];
    }

    public static function availableTypesOfLimit(): array
    {
        return [
            self::TYPE_OF_LIMIT_USER,
            self::TYPE_OF_LIMIT_ALL,
        ];
    }

    /**
     * Генерация нового промокода
     */
    public static function generate(): string
    {
        return mb_strtoupper(Str::random(10));
    }

    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class);
    }

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class);
    }

    public function setCustomerIds(array $customerIds): self
    {
        $conditions = $this->conditions ?? [];
        $conditions[self::CONDITION_TYPE_CUSTOMER_IDS] = $customerIds;
        $this->conditions = $conditions;
        return $this;
    }

    public function setSegmentIds(array $segmentIds): self
    {
        $conditions = $this->conditions ?? [];
        $conditions[self::CONDITION_TYPE_SEGMENT_IDS] = $segmentIds;
        $this->conditions = $conditions;
        return $this;
    }

    public function setRoleIds(array $roleIds): self
    {
        $conditions = $this->conditions ?? [];
        $conditions[self::CONDITION_TYPE_ROLE_IDS] = $roleIds;
        $this->conditions = $conditions;
        return $this;
    }

    public function getCustomerIds(): array
    {
        return $this->conditions[self::CONDITION_TYPE_CUSTOMER_IDS] ?? [];
    }

    public function getSegmentIds(): array
    {
        return $this->conditions[self::CONDITION_TYPE_SEGMENT_IDS] ?? [];
    }

    public function getRoleIds(): array
    {
        return $this->conditions[self::CONDITION_TYPE_ROLE_IDS] ?? [];
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where('end_date', '<', now())->whereNotNull('end_date');
    }

    /**
     * Активные и доступные на заданную дату скидки
     */
    public function scopeActive(Builder $query, ?Carbon $date = null): Builder
    {
        $date ??= Carbon::now();
        return $query
            ->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_TEST])
            ->where(function ($query) use ($date) {
                $query->where('start_date', '<=', $date)->orWhereNull('start_date');
            })->where(function ($query) use ($date) {
                $query->where('end_date', '>=', $date)->orWhereNull('end_date');
            });
    }

    /**
     * Найти по коду, учитывая регистр
     * @param Builder $query
     * @param string|array $code
     * @return Builder
     */
    public function scopeCaseSensitiveCode(Builder $query, string|array $code): Builder
    {
        return $query->whereIn(DB::raw('BINARY code'), Arr::wrap($code));
    }

    public static function boot()
    {
        parent::boot();

        self::saved(function (self $item) {

            $item->updateDiscountsPromocodeOnly();

            if ($item->owner_id) {
                $serviceNotificationService = app(ServiceNotificationService::class);

                /** @var UserService $userService */
                $userService = app(UserService::class);

                /** @var CustomerService $customerService */
                $customerService = app(CustomerService::class);

                $customer = $customerService->customers(
                    $customerService->newQuery()
                        ->setFilter('id', $item->owner_id)
                )->first();

                $user = $userService->users(
                    $userService->newQuery()
                        ->setFilter('id', $customer->user_id)
                )->first();

                switch ($item->status) {
                    case self::STATUS_CREATED:
                        $serviceNotificationService->send($customer->user_id, 'marketingovye_instrumentyzapros_na_vypusk_novogo_promo_koda_otpravlen');
                        break;
                    case self::STATUS_ACTIVE:
                        $serviceNotificationService->send($customer->user_id, 'marketingovye_instrumentyvypushchen_novyy_promo_kod', [
                            'NAME_PROMOKEY' => $item->name,
                            'LINK_NAME_PROMOKEY' => sprintf('%s/profile/promocodes', config('app.showcase_host')),
                            'CUSTOMER_NAME' => $user->first_name,
                        ]);
                        break;
                }

                return match ($item->status) {
                    self::STATUS_CREATED => $serviceNotificationService->sendToAdmin('aozpromokodpromokod_sformirovan'),
                    self::STATUS_EXPIRED => $serviceNotificationService->sendToAdmin('aozpromokodpromokod_otklyuchen'),
                    default => $serviceNotificationService->sendToAdmin('aozpromokodpromokod_izmenen'),
                };
            }
        });
    }

    /**
     * @return array
     */
    public function getDiscountIds(): array
    {
        return $this->discounts->pluck('id')->toArray();
    }

    /**
     * @return void
     */
    public function updateDiscountsPromocodeOnly(): void
    {
        $this->discounts()->each(function($discount) {
            $discount->promo_code_only = true;
            $discount->save();
        });
    }
}
