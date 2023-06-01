<?php

namespace App\Models\Price;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Цена на предложение мерчанта"
 * Class Price
 * @package App\Models\Price
 *
 * @property int $offer_id - id предложения
 * @property int $merchant_id - id мерчанта
 * @property double $price - цена проф
 * @property double $price_base - цена базовая
 * @property double $price_retail - цена розничная
 * @property string $updated_at - Дата и время последнего обновления
 */
class Price extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['merchant_id', 'offer_id', 'price', 'price_base', 'price_retail'];

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var string */
    protected $table = 'prices';
}
