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
 * @property bool $except
 *
 * @property-read Discount $discount
 */
class DiscountCategory extends AbstractModel
{
    use Hash;

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['discount_id', 'category_id', 'except'];

    /** @var array */
    protected $fillable = self::FILLABLE;

    protected $casts = [
        'except' => 'bool',
    ];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
