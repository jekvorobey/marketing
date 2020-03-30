<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pim\Services\SearchService\SearchService;

/**
 * Класс-модель для сущности "Скидка на товары бренда"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $brand_id
 * @property boolean $except
 * @property-read Discount $discount
 * @mixin \Eloquent
 */
class DiscountBrand extends AbstractModel
{
    use DiscountHash;

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'brand_id', 'except'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    protected static function boot()
    {
        parent::boot();

        self::saved(function (self $discountBrand) {
            $discountBrand->discount->updateProducts();
        });

        self::deleted(function (self $discountBrand) {
            $discountBrand->discount->updateProducts();
        });
    }
}
