<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Hash;

/**
 * Класс-модель для сущности "Скидка роли пользователя"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $role_id
 * @property boolean $except
 * @property-read Discount $discount
 * @mixin \Eloquent
 *
 */
class DiscountUserRole extends AbstractModel
{
    use Hash;

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'role_id'];

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

        self::saved(function (self $discountUserRole) {
            $discountUserRole->discount->updatePimContents();
        });

        self::deleted(function (self $discountUserRole) {
            $discountUserRole->discount->updatePimContents();
        });
    }
}
