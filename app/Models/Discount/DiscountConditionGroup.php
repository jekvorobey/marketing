<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Класс-модель для сущности "Группа условий скидки"
 * App\Models\Discount
 *
 * @property int $discount_id
 * @property int $logical_operator
 *
 * @property-read Collection|DiscountCondition[] $conditions
 */
class DiscountConditionGroup extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['discount_id', 'logical_operator'];

    /** @var array */
    protected $fillable = self::FILLABLE;

    public function conditions(): HasMany
    {
        return $this->hasMany(DiscountCondition::class);
    }
}
