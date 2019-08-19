<?php

namespace App\Models\Discount;

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
    protected $fillable = self::FILLABLE;

    /**
     * @var string
     */
    protected $table = 'discounts';

    protected $with = [
        'discountProduct',
        'discountProductBrand',
        'discountProductCategory',
        'discountUser',
        'discountUserRole',
        'discountDeliveryMethod',
        'discountPayMethod',
        'discountCartSumm',
        'discountReferralCode',
    ];

    public function discountProduct(){
        return $this->hasOne(DiscountProduct::class, 'discount_id');
    }

    public function discountProductBrand(){
        return $this->hasOne(DiscountProductBrand::class, 'discount_id');
    }

    public function discountProductCategory(){
        return $this->hasOne(DiscountProductCategory::class, 'discount_id');
    }

    public function discountUser(){
        return $this->hasOne(DiscountUser::class, 'discount_id');
    }

    public function discountUserRole(){
        return $this->hasOne(DiscountUserRole::class, 'discount_id');
    }

    public function discountDeliveryMethod(){
        return $this->hasOne(DiscountDeliveryMethod::class, 'discount_id');
    }

    public function discountPayMethod(){
        return $this->hasOne(DiscountPayMethod::class, 'discount_id');
    }

    public function discountCartSumm(){
        return $this->hasOne(DiscountCartSumm::class, 'discount_id');
    }

    public function discountReferralCode(){
        return $this->hasOne(DiscountReferralCode::class, 'discount_id');
    }
}
