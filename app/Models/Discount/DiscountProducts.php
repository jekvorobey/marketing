<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка продукта"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $offer_id
 * @mixin \Eloquent
 *
 */
class DiscountProducts extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'offer_id'];

    /**
     * @var array
     */
    protected $fillable = ['discount_id', 'offer_id'];

    /**
     * @var string
     */
    protected $table = 'discount_products';
}
