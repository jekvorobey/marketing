<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка на товары бренда"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $brand_id
 * @property boolean $except
 * @mixin \Eloquent
 *
 */
class DiscountBrand extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'brand_id', 'except'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
}
