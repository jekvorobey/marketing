<?php

namespace App\Models\Discount;

use App\Models\Hash;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Класс-модель для сущности "Скидка на свойство товара"
 * App\Models\Discount\DiscountProductProperty
 *
 * @property int $discount_id
 * @property int $property_id
 * @property int[] $values
 * @property bool $except
 * @property-read Discount $discount
 */
class DiscountProductProperty extends AbstractModel
{
    use Hash;

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['discount_id', 'property_id', 'values', 'except'];

    /** @var array */
    protected $fillable = self::FILLABLE;

    protected $casts = [
        'except' => 'bool',
        'values' => 'array',
    ];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
