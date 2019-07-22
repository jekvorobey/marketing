<?php

namespace App\Models;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка пользователя"
 * App\Models\Discount
 *
 * @property int $discount_id
 * @property int $user_id
 * @mixin \Eloquent
 *
 */
class DiscountUser extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'user_id'];
    
    /**
     * @var array
     */
    protected $fillable = ['discount_id', 'user_id'];
    
    /**
     * @var string
     */
    protected $table = 'discount_users';
}
