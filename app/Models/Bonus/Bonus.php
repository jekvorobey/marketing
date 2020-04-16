<?php

namespace App\Models\Bonus;

use Carbon\Carbon;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Bonus
 * @package App\Models\Bonus
 *
 * @property string $name
 * @property int $status
 * @property int $type
 * @property int $value_type
 * @property int $value
 * @property int $valid_period # Срок жизни бонусов (в днях)
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property boolean $promo_code_only
 *
 * @property-read Collection|BonusOffer[] $offers
 * @property-read Collection|BonusBrand[] $brands
 * @property-read Collection|BonusCategory[] $categories
 */
class Bonus extends AbstractModel
{
    /**
     * Статус бонуса
     */
    /** Создана */
    const STATUS_CREATED = 1;
    /** Активна */
    const STATUS_ACTIVE = 2;
    /** Приостановлена */
    const STATUS_PAUSED = 3;
    /** Завершена */
    const STATUS_EXPIRED = 4;

    /**
     * Тип бонуса
     */
    /** Бонус на оффер */
    const TYPE_OFFER = 1;
    /** Бонус на бренд */
    const TYPE_BRAND = 2;
    /** Бонус на категорию */
    const TYPE_CATEGORY = 3;
    /** Бонус на услугу */
    const TYPE_SERVICE = 4;
    /** Бонус на сумму корзины */
    const TYPE_CART_TOTAL = 5;

    /** Тип значения – Проценты */
    const VALUE_TYPE_PERCENT = 1;
    /** Тип значения – Рубли */
    const VALUE_TYPE_RUB = 2;

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'name',
        'status',
        'type',
        'value',
        'value_type',
        'valid_period',
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
     * Доступные типы бонусов
     * @return array
     */
    public static function availableTypes()
    {
        return [
            self::TYPE_OFFER,
            self::TYPE_BRAND,
            self::TYPE_CATEGORY,
            self::TYPE_SERVICE,
            self::TYPE_CART_TOTAL,
        ];
    }

    /**
     * Доступные статусы бонусов
     * @return array
     */
    public static function availableStatuses()
    {
        return [
            self::STATUS_CREATED,
            self::STATUS_ACTIVE,
            self::STATUS_PAUSED,
            self::STATUS_EXPIRED,
        ];
    }

    /**
     * @return HasMany
     */
    public function offers()
    {
        return $this->hasMany(BonusOffer::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function brands()
    {
        return $this->hasMany(BonusBrand::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function categories()
    {
        return $this->hasMany(BonusCategory::class, 'discount_id');
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
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($query) use ($date) {
                $query->where('start_date', '<=', $date)->orWhereNull('start_date');
            })->where(function ($query) use ($date) {
                $query->where('end_date', '>=', $date)->orWhereNull('end_date');
            });
    }
}
