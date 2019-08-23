<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка категории продукта"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $category_id
 * @mixin \Eloquent
 *
 */
class DiscountProductCategory extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'category_id'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /**
     * @var string
     */
    protected $table = 'discount_product_categories';
}
