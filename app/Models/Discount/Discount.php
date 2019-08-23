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
        return $this->hasMany(DiscountProduct::class, 'discount_id');
    }

    public function discountProductBrand(){
        return $this->hasMany(DiscountProductBrand::class, 'discount_id');
    }

    public function discountProductCategory(){
        return $this->hasMany(DiscountProductCategory::class, 'discount_id');
    }

    public function discountUser(){
        return $this->hasMany(DiscountUser::class, 'discount_id');
    }

    public function discountUserRole(){
        return $this->hasMany(DiscountUserRole::class, 'discount_id');
    }

    public function discountDeliveryMethod(){
        return $this->hasMany(DiscountDeliveryMethod::class, 'discount_id');
    }

    public function discountPayMethod(){
        return $this->hasMany(DiscountPayMethod::class, 'discount_id');
    }

    public function discountCartSumm(){
        return $this->hasMany(DiscountCartSumm::class, 'discount_id');
    }

    public function discountReferralCode(){
        return $this->hasMany(DiscountReferralCode::class, 'discount_id');
    }
}
