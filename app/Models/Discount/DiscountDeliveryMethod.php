<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка для способа доставки"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $delivery_method_id
 * @mixin \Eloquent
 *
 */
class DiscountDeliveryMethod extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'delivery_method_id'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /**
     * @var string
     */
    protected $table = 'discount_delivery_methods';
}
