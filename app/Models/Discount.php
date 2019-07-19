<?php

namespace App\Models;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка"
 * App\Models\Discount
 *
 * @property int $type
 * @property string|null $name
 * @property int $value_type
 * @property int $value
 * @property int|null $region_id
 * @property int $status
 * @mixin \Eloquent
 *
 */
class Discount extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['type', 'name', 'value_type', 'value', 'region_id'];
    
    /**
     * @var array
     */
    protected $fillable = ['type', 'name', 'value_type', 'value', 'region_id'];
    
    /**
     * @var string
     */
    protected $table = 'discounts';
}
