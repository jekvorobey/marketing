<?php

namespace App\Models\Discount;

use Carbon\Carbon;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Eloquent;
use DB;

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
 * @property-read Collection|DiscountBrand[] $brands
 * @property-read Collection|DiscountCategory[] $categories
 * @property-read Collection|DiscountUserRole[] $roles
 * @property-read Collection|DiscountUserRole[] $segments
 * @property-read Collection|DiscountCondition[] $conditions
 *
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
            case self::DISCOUNT_TYPE_BUNDLE:
            case self::DISCOUNT_TYPE_BRAND:
            case self::DISCOUNT_TYPE_CATEGORY:
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
     * @return HasMany
     */
    public function conditions()
    {
        return $this->hasMany(DiscountCondition::class, 'discount_id');
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
            ->where('status', Discount::STATUS_ACTIVE)
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
                self::DISCOUNT_TYPE_BRAND,
                self::DISCOUNT_TYPE_CATEGORY,
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
     * Проверяет корректные ли данные хранятся в сущнсоти Discount
     * (не проверяет корректность связанных сущностей)
     * @return bool
     */
    public function validate(): bool
    {
        return $this->value >= 1 &&
            in_array($this->type, [
                self::DISCOUNT_TYPE_OFFER,
                self::DISCOUNT_TYPE_BUNDLE,
                self::DISCOUNT_TYPE_BRAND,
                self::DISCOUNT_TYPE_CATEGORY,
                self::DISCOUNT_TYPE_DELIVERY,
                self::DISCOUNT_TYPE_CART_TOTAL,
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

        self::deleting(function (Discount $item) {
            $synergy = DiscountCondition::query()
                ->where('discount_id', $item->id)
                ->where('type', DiscountCondition::DISCOUNT_SYNERGY)
                ->first();

            if ($synergy) {
                $synergy->delete();
            }
        });
    }
}
