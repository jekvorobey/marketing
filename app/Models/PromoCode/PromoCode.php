<?php

namespace App\Models\PromoCode;

use App\Models\Discount\Discount;
use Carbon\Carbon;
use Faker\Factory;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class PromoCode
 * @package App\Models\PromoCode
 *
 * @property int $creator_id
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
}
