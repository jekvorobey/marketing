<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Скидка для реферала"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property string $referral_code
 * @mixin \Eloquent
 *
 */
class DiscountReferralCode extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'referral_code'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /**
     * @var string
     */
    protected $table = 'discount_referral_codes';
}
