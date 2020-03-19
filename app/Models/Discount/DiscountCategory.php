<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка на товары категории"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $category_id
 * @mixin \Eloquent
 *
 */
class DiscountCategory extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'category_id'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
}
