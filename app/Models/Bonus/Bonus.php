<?php

namespace App\Models\Bonus;

use App\Models\PromoCode\PromoCode;
use Carbon\Carbon;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Pim\Services\SearchService\SearchService;

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
 * @property bool $promo_code_only
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
    public const STATUS_CREATED = 1;

    /** Активна */
    public const STATUS_ACTIVE = 2;

    /** Приостановлена */
    public const STATUS_PAUSED = 3;

    /** Завершена */
    public const STATUS_EXPIRED = 4;

    /**
     * Тип бонуса
     */
    /** Бонус на оффер */
    public const TYPE_OFFER = 1;

    /** Бонус на бренд */
    public const TYPE_BRAND = 2;

    /** Бонус на категорию */
    public const TYPE_CATEGORY = 3;

    /** Бонус на услугу */
    public const TYPE_SERVICE = 4;

    /** Бонус на сумму корзины */
    public const TYPE_CART_TOTAL = 5;

    /** Бонус на все офферы */
    public const TYPE_ANY_OFFER = 6;

    /** Бонус на все бренды */
    public const TYPE_ANY_BRAND = 7;

    /** Бонус на все категории */
    public const TYPE_ANY_CATEGORY = 8;

    /** Бонус на все услуги */
    public const TYPE_ANY_SERVICE = 9;

    /** Тип значения – Проценты */
    public const VALUE_TYPE_PERCENT = 1;

    /** Тип значения – Абсолютное значение (в бонусах) */
    public const VALUE_TYPE_ABSOLUTE = 2;

    public const BONUS_OFFER_RELATION = 'offers';
    public const BONUS_BRAND_RELATION = 'brands';
    public const BONUS_CATEGORY_RELATION = 'categories';

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = [
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

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var array */
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
            self::TYPE_ANY_OFFER,
            self::TYPE_ANY_BRAND,
            self::TYPE_ANY_CATEGORY,
            self::TYPE_ANY_SERVICE,
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
     * @return array
     */
    public static function availableRelations()
    {
        return [
            Bonus::BONUS_OFFER_RELATION,
            Bonus::BONUS_BRAND_RELATION,
            Bonus::BONUS_CATEGORY_RELATION,
        ];
    }

    /**
     * @return array
     */
    public function getMappingRelations()
    {
        return [
            Bonus::BONUS_OFFER_RELATION => ['class' => BonusOffer::class, 'items' => $this->offers],
            Bonus::BONUS_BRAND_RELATION => ['class' => BonusBrand::class, 'items' => $this->brands],
            Bonus::BONUS_CATEGORY_RELATION => ['class' => BonusCategory::class, 'items' => $this->categories],
        ];
    }

    /**
     * @return HasMany
     */
    public function offers()
    {
        return $this->hasMany(BonusOffer::class, 'bonus_id');
    }

    /**
     * @return HasMany
     */
    public function brands()
    {
        return $this->hasMany(BonusBrand::class, 'bonus_id');
    }

    /**
     * @return HasMany
     */
    public function categories()
    {
        return $this->hasMany(BonusCategory::class, 'bonus_id');
    }

    /**
     * @return HasMany
     */
    public function promoCodes()
    {
        return $this->hasMany(PromoCode::class, 'bonus_id');
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
     */
    public function scopeActive(Builder $query, ?Carbon $date = null): Builder
    {
        $date ??= Carbon::now();
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($query) use ($date) {
                $query->where('start_date', '<=', $date)->orWhereNull('start_date');
            })->where(function ($query) use ($date) {
                $query->where('end_date', '>=', $date)->orWhereNull('end_date');
            });
    }

    public static function boot()
    {
        parent::boot();

        self::saved(function (self $bonus) {
            $bonus->updateProducts();
        });

        self::deleting(function (self $bonus) {
            $bonus->updateProducts();
        });
    }

    public function updateProducts()
    {
        static $actionPerformed = false;
        if (!$actionPerformed) {
            /** @var SearchService $searchService */
            $searchService = resolve(SearchService::class);
            $searchService->markAllProductsForIndex();
            $actionPerformed = true;
        }
    }
}
