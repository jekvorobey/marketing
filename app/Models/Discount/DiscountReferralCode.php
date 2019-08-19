<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка для
 *
 * "
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property float $min_summ
 * @mixin \Eloquent
 *
 */
class DiscountReferralCode extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'min_summ'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /**
     * @var string
     */
    protected $table = 'discount_cart_summ';
}
