<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Hash;

/**
 * Класс-модель для сущности "Скидка на мастер-класс"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id - ID скидки
 * @property int $ticket_type_id - ID типа билета на мастер-класс
 * @property-read Discount $discount
 */
class DiscountPublicEvent extends AbstractModel
{
    use Hash;

    protected $table = 'discount_public_events';

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['discount_id', 'ticket_type_id'];

    /** @var array */
    protected $fillable = self::FILLABLE;

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
