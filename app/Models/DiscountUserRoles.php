<?php

namespace App\Models;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка роли пользователя"
 * App\Models\Discount
 *
 * @property int $discount_id
 * @property int $role_id
 * @mixin \Eloquent
 *
 */
class DiscountUserRoles extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'role_id'];
    
    /**
     * @var array
     */
    protected $fillable = ['discount_id', 'role_id'];
    
    /**
     * @var string
     */
    protected $table = 'discount_user_roles';
}
