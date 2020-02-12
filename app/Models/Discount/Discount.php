<?php

namespace App\Models\Discount;

use Carbon\Carbon;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Класс-модель для сущности "Скидка"
 * App\Models\Discount
 *
 * @property int $sponsor
 * @property int $merchant_id
 * @property int $type
 * @property string|null $name
 * @property int $value_type
 * @property int $value
 * @property int $approval_status
 * @property int $status
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property boolean $promo_code_only
 * @mixin \Eloquent
 *
 * @property-read Collection|DiscountOffer[] $offers
 * @property-read Collection|DiscountBrand[] $brands
 * @property-read Collection|DiscountCategory[] $categories
 * @property-read Collection|DiscountUserRole[] $roles
 * @property-read Collection|DiscountUserRole[] $segments
 *
 */
class Discount extends AbstractModel
{
    /**
     * Статус согласования скидки
     */
    /** Не согласовано */
    const APP_STATUS_NOT_APPROVED = 1;
    /** Отправлено */
    const APP_STATUS_SENT = 2;
    /** На рассмотрении */
    const APP_STATUS_APPROVING = 3;
    /** Отклонено */
    const APP_STATUS_REJECT = 4;
    /** Согласовано */
    const APP_STATUS_APPROVED = 5;

    /**
     * Статус скидки
     */
    /** Активна */
    const STATUS_ACTIVE = 1;
    /** Приостановлена */
    const STATUS_PAUSED = 2;
    /** Завершена */
    const STATUS_EXPIRED = 3;

    /**
     * Тип скидки (назначается на)
     */
    /** Скидка на оффер */
    const DISCOUNT_TYPE_OFFER = 1;
    /** Скидка на бандл */
    const DISCOUNT_TYPE_BUNDLE = 2;
    /** Скидка на бренд */
    const DISCOUNT_TYPE_BRAND = 3;
    /** Скидка на категорию */
    const DISCOUNT_TYPE_CATEGORY = 4;
    /** Скидка на доставку */
    const DISCOUNT_TYPE_DELIVERY = 5;
    /** Скидка на сумму корзины */
    const DISCOUNT_TYPE_CART_TOTAL = 6;

    /** Спонсор скидки */
    const DISCOUNT_MERCHANT_SPONSOR = 1;
    const DISCOUNT_ADMIN_SPONSOR = 2;

    /** Тип значения – Проценты */
    const DISCOUNT_VALUE_TYPE_PERCENT = 1;
    /** Тип значения – Рубли */
    const DISCOUNT_VALUE_TYPE_RUB = 2;

    /**
     * Тип условия возникновения права на скидку
     */
    /** На первый заказ */
    const CONDITION_TYPE_FIRST_ORDER = 1;
    /** На заказ от определенной суммы */
    const CONDITION_TYPE_MIN_PRICE_ORDER = 2;
    /** На заказ от определенной суммы товаров заданного бренда */
    const CONDITION_TYPE_MIN_PRICE_BRAND = 3;
    /** На заказ от определенной суммы товаров заданной категории */
    const CONDITION_TYPE_MIN_PRICE_CATEGORY = 4;
    /** На количество единиц одного товара */
    const CONDITION_TYPE_EVERY_UNIT_PRODUCT = 5;
    /** На способ доставки */
    const CONDITION_TYPE_DELIVERY_METHOD = 6;
    /** На способ оплаты */
    const CONDITION_TYPE_PAY_METHOD = 7;
    /** Территория действия (регион с точки зрения адреса доставки заказа) */
    const CONDITION_TYPE_REGION = 8;
    /** Для определенных пользователей системы */
    const CONDITION_TYPE_USER = 9;
    /** Порядковый номер заказа */
    const CONDITION_TYPE_ORDER_SEQUENCE_NUMBER = 10;
    /** Взаимодействия с другими маркетинговыми инструментами */
    const CONDITION_TYPE_DISCOUNT_SYNERGY = 11;

    const DISCOUNT_OFFER_RELATION = 1;
    const DISCOUNT_BRAND_RELATION = 2;
    const DISCOUNT_CATEGORY_RELATION = 3;
    const DISCOUNT_SEGMENT_RELATION = 4;
    const DISCOUNT_USER_ROLE_RELATION = 5;
    const DISCOUNT_CONDITION_RELATION = 6;

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'sponsor',
        'merchant_id',
        'type',
        'name',
        'value_type',
        'value',
        'approval_status',
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
     * Доступные типы скидок
     * @return array
     */
    public static function availableTypes()
    {
        return [
            self::DISCOUNT_TYPE_OFFER,
            self::DISCOUNT_TYPE_BUNDLE,
            self::DISCOUNT_TYPE_BRAND,
            self::DISCOUNT_TYPE_CATEGORY,
            self::DISCOUNT_TYPE_DELIVERY,
            self::DISCOUNT_TYPE_CART_TOTAL,
        ];
    }

    /**
     * Доступные статусы скидок
     * @return array
     */
    public static function availableStatuses()
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_PAUSED,
            self::STATUS_EXPIRED,
        ];
    }

    /**
     * Доступные статусы рассмотрения заявок на скидки
     * @return array
     */
    public static function availableAppStatuses()
    {
        return [
            self::APP_STATUS_NOT_APPROVED,
            self::APP_STATUS_SENT,
            self::APP_STATUS_APPROVING,
            self::APP_STATUS_REJECT,
            self::APP_STATUS_APPROVED,
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
}
