<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка роли пользователя"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $role_id
 * @mixin \Eloquent
 *
 */
class DiscountUserRole extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'role_id'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
}
