<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Hash;

/**
 * Класс-модель для сущности "Элемент бандла"
 * App\Models\Discount\BundleItem
 *
 * @property int $discount_id
 * @property int $item_id
 * @property-read Discount $discount
 *
 */
class BundleItem extends AbstractModel
{
    use Hash;

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'item_id'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
