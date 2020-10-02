<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Hash;

/**
 * Класс-модель для сущности "Скидка на товары категории"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $category_id
 * @property-read Discount $discount
 * @mixin \Eloquent
 *
 */
class DiscountCategory extends AbstractModel
{
    use Hash;

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'category_id'];

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

        self::saved(function (self $discountCategory) {
            $discountCategory->discount->updatePimContents();
        });

        self::deleted(function (self $discountCategory) {
            $discountCategory->discount->updatePimContents();
        });
    }
}
