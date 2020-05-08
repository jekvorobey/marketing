<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Hash;

/**
 * Класс-модель для сущности "Скидка сегмента пользователей"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $segment_id
 * @property boolean $except
 * @property-read Discount $discount
 * @mixin \Eloquent
 *
 */
class DiscountSegment extends AbstractModel
{
    use Hash;

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'segment_id'];

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

        self::saved(function (self $discountSegment) {
            $discountSegment->discount->updateProducts();
        });

        self::deleted(function (self $discountSegment) {
            $discountSegment->discount->updateProducts();
        });
    }
}
