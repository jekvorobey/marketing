<?php

namespace App\Models\Bonus\ProductBonusOption;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Class ProductBonusOption
 * @package App\Models\Bonus
 *
 * @property int $product_id
 * @property int $max_percentage_payment
 */
class ProductBonusOption extends AbstractModel
{
    /** Максимальный процент от единицы товара, который можно оплатить бонусами */
    const MAX_PERCENTAGE_PAYMENT = 'max_percentage_payment';

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'product_id',
        'value',
    ];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /**
     * @var array
     */
    protected $casts = [
        'value' => 'array',
    ];
}
