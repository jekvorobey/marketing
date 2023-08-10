<?php

namespace App\Models\Price;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Class PriceByRole
 * @package App\Models\PriceByRole
 *
 * @property int $price_id - id базовой цены
 * @property int $role - id роли
 * @property double $price - цена для оффера и роли
 * @property float $percent_by_base_price - Значение наценки на цену
 * @property string $updated_at - Дата и время последнего обновления
 */
class PriceByRole extends AbstractModel
{
    public const FILLABLE = ['price_id', 'role', 'price', 'percent_by_base_price'];

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var string */
    protected $table = 'prices_by_roles';

    public function basePrice(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }
}
