<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Условие возникновения скидки"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $type
 * @property array $condition
 * @mixin \Eloquent
 *
 */
class DiscountCondition extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'type', 'condition'];

    /**
     * @var array
     */
    protected $casts = [
        'condition' => 'array',
    ];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
}
