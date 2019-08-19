<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка бренда продукта"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $brand_id
 * @mixin \Eloquent
 *
 */
class DiscountProductBrand extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'brand_id'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /**
     * @var string
     */
    protected $table = 'discount_product_brands';
}
