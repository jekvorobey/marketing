<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка сегмента пользователей"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $segment_id
 * @property boolean $except
 * @mixin \Eloquent
 *
 */
class DiscountSegment extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'segment_id', 'except'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
}
