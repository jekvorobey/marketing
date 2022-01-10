<?php

namespace App\Models\Discount;

use App\Models\Hash;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Класс-модель для сущности "Скидка на бандлы"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $bundle_id
 * @property bool $except
 *
 * @property-read Discount $discount
 * @mixin \Eloquent
 */
class DiscountBundle extends AbstractModel
{
    use Hash;

    /**
     * Заполняемые поля модели
     */
    protected $fillable = ['discount_id', 'bundle_id', 'except'];

    protected $casts = [
        'except' => 'bool',
    ];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
