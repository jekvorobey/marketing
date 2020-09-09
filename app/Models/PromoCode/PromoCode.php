<?php

namespace App\Models\PromoCode;

use App\Models\Bonus\Bonus;
use App\Models\Discount\Discount;
use Carbon\Carbon;
use Faker\Factory;
use Greensight\CommonMsa\Models\AbstractModel;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $status
 * @property int $type
 * @property int|null $discount_id
 * @property int|null $gift_id
 * @property int|null $bonus_id
 * @property array $conditions
 * @mixin Eloquent
 *
 * @property-read Discount|null $discount
 * @property-read Bonus|null $bonus
 */
class PromoCode extends AbstractModel
{
    /**
     * Статус промокода
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
    /** Тестовый */
    const STATUS_TEST = 8;

    /**
     * Тип промокода (на что промокод)
     */
    /** Промокод на скидку */
    const TYPE_DISCOUNT = 1;
    /** Промокод на бесплатную доставку */
    const TYPE_DELIVERY = 2;
    /** Промокод на подарок */
    const TYPE_GIFT = 3;
    /** Промокод на бонусы */
    const TYPE_BONUS = 4;

    /**
     * Тип условия для применения промокода
     */
    /** Для определенного(ых) пользователя(ей) */
    const CONDITION_TYPE_CUSTOMER_IDS = 'customers';
    /** Для определенного(ых) сегмента(ов) */
    const CONDITION_TYPE_SEGMENT_IDS = 'segments';
    /** Для определенной(ых) роли(ей) */
    const CONDITION_TYPE_ROLE_IDS = 'roles';

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'creator_id',
        'merchant_id',
        'owner_id',
        'name',
        'code',
        'counter',
        'start_date',
        'end_date',
        'status',
        'type',
        'discount_id',
        'gift_id',
        'bonus_id',
        'conditions',
    ];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /**
     * @var array
     */
    protected $casts = [
        'conditions' => 'array',
    ];

    /**
     * Доступные статусы промокодов
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
            self::STATUS_TEST,
        ];
    }

    /**
     * Типы промокодов
     * @return array
     */
    public static function availableTypes()
    {
        return [
            self::TYPE_DISCOUNT,
            self::TYPE_DELIVERY,
            self::TYPE_GIFT,
            self::TYPE_BONUS
        ];
    }

    /**
     * @return array
     */
    public static function availableTypesForMerchant()
    {
        return [
            self::TYPE_DISCOUNT,
            self::TYPE_GIFT,
            self::TYPE_BONUS
        ];
    }

    /**
     * Генерация нового промокода
     */
    public static function generate()
    {
        return Factory::create('ru_RU')->regexify('[A-Z0-9]{10}');
    }

    /**
     * @return BelongsTo
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    /**
     * @return BelongsTo
     */
    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class);
    }

    /**
     * @param array $customerIds
     * @return PromoCode
     */
    public function setCustomerIds(array $customerIds)
    {
        $conditions = $this->conditions ?? [];
        $conditions[self::CONDITION_TYPE_CUSTOMER_IDS] = $customerIds;
        $this->conditions = $conditions;
        return $this;
    }

    /**
     * @param array $segmentIds
     * @return PromoCode
     */
    public function setSegmentIds(array $segmentIds)
    {
        $conditions = $this->conditions ?? [];
        $conditions[self::CONDITION_TYPE_SEGMENT_IDS] = $segmentIds;
        $this->conditions = $conditions;
        return $this;
    }

    /**
     * @param array $roleIds
     * @return PromoCode
     */
    public function setRoleIds(array $roleIds)
    {
        $conditions = $this->conditions ?? [];
        $conditions[self::CONDITION_TYPE_ROLE_IDS] = $roleIds;
        $this->conditions = $conditions;
        return $this;
    }

    /**
     * @return array
     */
    public function getCustomerIds()
    {
        return $this->conditions[self::CONDITION_TYPE_CUSTOMER_IDS] ?? [];
    }

    /**
     * @return array
     */
    public function getSegmentIds()
    {
        return $this->conditions[self::CONDITION_TYPE_SEGMENT_IDS] ?? [];
    }

    /**
     * @return array
     */
    public function getRoleIds()
    {
        return $this->conditions[self::CONDITION_TYPE_ROLE_IDS] ?? [];
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
            ->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_TEST])
            ->where(function ($query) use ($date) {
                $query->where('start_date', '<=', $date)->orWhereNull('start_date');
            })->where(function ($query) use ($date) {
                $query->where('end_date', '>=', $date)->orWhereNull('end_date');
            });
    }

    public static function boot()
    {
        parent::boot();

        self::saved(function (self $item) {
            /**
             * Скидка доступна только по промокоду
             */
            if ($item->discount_id) {
                /** @var Discount $discount */
                $discount = Discount::find($item->discount_id);
                if ($discount && !$discount->promo_code_only) {
                    $discount->promo_code_only = true;
                    $discount->save();
                }
            }

            $serviceNotificationService = app(ServiceNotificationService::class);

            /** @var UserService */
            $userService = app(UserService::class);
            $user = $userService->users(
                $userService->newQuery()
                    ->setFilter('id', $item->creator_id)
            )->first();

            switch ($item->status) {
                case self::STATUS_CREATED:
                    $serviceNotificationService->send($item->creator_id, 'marketingovye_instrumentyzapros_na_vypusk_novogo_promo_koda_otpravlen');
                    return $serviceNotificationService->sendToAdmin('aozpromokodpromokod_sformirovan');
                case self::STATUS_ACTIVE:
                    return $serviceNotificationService->send($item->creator_id, 'marketingovye_instrumentyvypushchen_novyy_promo_kod', [
                        'NAME_PROMOKEY' => $item->name,
                        'LINK_NAME_PROMOKEY' => sprintf('%s/profile/account', config('app.showcase_host')),
                        'CUSTOMER_NAME' => $user->first_name
                    ]);
                case self::STATUS_EXPIRED:
                    return $serviceNotificationService->sendToAdmin('aozpromokodpromokod_otklyuchen');
                default:
                    return $serviceNotificationService->sendToAdmin('aozpromokodpromokod_izmenen');
            }
        });
    }
}
