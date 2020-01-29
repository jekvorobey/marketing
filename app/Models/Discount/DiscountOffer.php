<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка на оффер"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $offer_id
 * @mixin \Eloquent
 *
 */
class DiscountOffer extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'offer_id'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
}
