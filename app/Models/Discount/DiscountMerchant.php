<?php

namespace App\Models\Discount;

use App\Models\Hash;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Класс-модель для сущности "Скидка на мерчанта"
 * App\Models\Discount\DiscountMerchant
 *
 * @property int $discount_id
 * @property int $merchant_id
 * @property bool $except
 * @property-read Discount $discount
 */
class DiscountMerchant extends AbstractModel
{
    use Hash;

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['discount_id', 'merchant_id', 'except'];

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
