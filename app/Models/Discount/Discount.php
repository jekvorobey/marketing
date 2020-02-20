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
     * Сделать скидку совместимой с другой скидкой
     * @param Discount $other
     * @return bool
     */
    public function makeCompatible(Discount $other)
    {
        if ($this->id === $other->id) {
            return false;
        }

        try {
            DB::beginTransaction();

            /** @var DiscountCondition[] $conditions */
            $conditions = DiscountCondition::query()
                ->whereIn('discount_id', [$this->id, $other->id])
                ->where('type', DiscountCondition::DISCOUNT_SYNERGY)
                ->get()
                ->keyBy('discount_id');

            $thisSynergy = collect($conditions[$this->id]['condition'][DiscountCondition::FIELD_SYNERGY] ?? [])
                ->push($other->id)
                ->unique()
                ->toArray();

            $otherSynergy = collect($conditions[$other->id]['condition'][DiscountCondition::FIELD_SYNERGY] ?? [])
                ->push($this->id)
                ->unique()
                ->toArray();

            if ($conditions->has($this->id)) {
                $conditions[$this->id]->condition = [DiscountCondition::FIELD_SYNERGY => $thisSynergy];
                $conditions[$this->id]->update();
            } else {
                DiscountCondition::create([
                    'discount_id' => $this->id,
                    'type' => DiscountCondition::DISCOUNT_SYNERGY,
                    'condition' => [DiscountCondition::FIELD_SYNERGY => $thisSynergy]
                ]);
            }

            if ($conditions->has($other->id)) {
                $conditions[$other->id]->condition = [DiscountCondition::FIELD_SYNERGY => $otherSynergy];
                $conditions[$other->id]->update();
            } else {
                DiscountCondition::create([
                    'discount_id' => $other->id,
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
}
