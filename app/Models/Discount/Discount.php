<?php

namespace App\Models\Discount;

use Carbon\Carbon;
use Greensight\CommonMsa\Models\AbstractModel;
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
 * @property-read Collection|DiscountOffer[] $discountProduct
 * @property-read Collection|DiscountBrand[] $discountProductBrand
 * @property-read Collection|DiscountCategory[] $discountProductCategory
 * @property-read Collection|DiscountUserRole[] $discountUserRole
 */
class Discount extends AbstractModel
{
    /**
     * Статус согласования скидки
     */
    /** Отправлено */
    const APP_STATUS_SENT = 1;
    /** На рассмотрении */
    const APP_STATUS_APPROVING = 2;
    /** Отклонено */
    const APP_STATUS_REJECT = 3;
    /** Согласовано */
    const APP_STATUS_APPROVED = 4;

    /**
     * Статус скидки
     */
    /** Активна */
    const STATUS_ACTIVE = 1;
    /** Приостановлена */
    const STATUS_PAUSED = 2;
    /** Истекла */
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
    /** Скидка на все товары */
    const DISCOUNT_TYPE_CART_TOTAL = 6;

    /**
     * Тип условия возникновения права на скидку
     */
    /** На первый заказ */
    const CONDITION_TYPE_FIRST_ORDER = 1;
    /** На заказ от определенной суммы */
    const CONDITION_TYPE_MIN_PRICE_ORDER = 2;
    /** На заказ от определенной суммы товаров заданного бренда */
    const CONDITION_TYPE_MIN_PRICE_BRAND = 3;
    /** На заказ от определенной суммы товаров заданной категорииа */
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

    protected $with = [
        'discountOffer',
        'discountBrand',
        'discountCategory',
        'discountUserRole',
        'discountSegment',
    ];

    /**
     * @return HasMany
     */
    public function discountOffer()
    {
        return $this->hasMany(DiscountOffer::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function discountBrand()
    {
        return $this->hasMany(DiscountBrand::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function discountCategory()
    {
        return $this->hasMany(DiscountCategory::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function discountUserRole()
    {
        return $this->hasMany(DiscountUserRole::class, 'discount_id');
    }

    /**
     * @return HasMany
     */
    public function discountSegment()
    {
        return $this->hasMany(DiscountSegment::class, 'discount_id');
    }
}
