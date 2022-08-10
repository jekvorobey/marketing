<?php

namespace App\Models\Bonus\ProductBonusOption;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Class ProductBonusOption
 * @package App\Models\Bonus
 *
 * @property int $product_id
 * @property int $max_percentage_payment
 * @property array $value
 */
class ProductBonusOption extends AbstractModel
{
    /** Максимальный процент от единицы товара, который можно оплатить бонусами */
    public const MAX_PERCENTAGE_PAYMENT = 'max_percentage_payment';

    /** Максимальный процент от единицы товара со скидкой, который можно оплатить бонусами */
    public const MAX_PERCENTAGE_DISCOUNT_PAYMENT = 'max_percentage_discount_payment';

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = [
        'product_id',
        'value',
    ];

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var array */
    protected $casts = [
        'value' => 'array',
    ];
}
