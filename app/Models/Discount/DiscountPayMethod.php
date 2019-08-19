<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка для способа оплаты"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $payment_method_id
 * @mixin \Eloquent
 *
 */
class DiscountPayMethod extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'payment_method_id'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /**
     * @var string
     */
    protected $table = 'discount_pay_methods';
}
