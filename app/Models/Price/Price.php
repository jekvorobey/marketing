<?php

namespace App\Models\Price;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Цена на предложение мерчанта"
 * Class Price
 * @package App\Models\Price
 *
 * @property int $offer_id - id предложения
 * @property double $price - цена
 */
class Price extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['offer_id', 'price'];
    
    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
    
    /**
     * @var string
     */
    protected $table = 'prices';
}
