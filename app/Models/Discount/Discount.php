<?php

namespace App\Models\Discount;

use Carbon\Carbon;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Collection;

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
 * @property-read Collection|DiscountProduct[] $discountProduct
 * @property-read Collection|DiscountProductBrand[] $discountProductBrand
 * @property-read Collection|DiscountProductCategory[] $discountProductCategory
 * @property-read Collection|DiscountUser[] $discountUser
 * @property-read Collection|DiscountUserRole[] $discountUserRole
 * @property-read Collection|DiscountDeliveryMethod[] $discountDeliveryMethod
 * @property-read Collection|DiscountPayMethod[] $discountPayMethod
 * @property-read Collection|DiscountCartSumm[] $discountCartSumm
 * @property-read Collection|DiscountReferralCode[] $discountReferralCode
 */
class Discount extends AbstractModel
{
    const APP_STATUS_NOT_APPROVED = 1;
    const APP_STATUS_SENT = 2;
    const APP_STATUS_APPROVING = 3;
    const APP_STATUS_REJECT = 4;
    const APP_STATUS_APPROVED = 5;
    
    const STATUS_ACTIVE = 1;
    const STATUS_PAUSED = 2;
    const STATUS_EXPIRED = 3;
    
    const TYPE_PRODUCT = 1;
    const TYPE_PRODUCT_CATEGORY = 2;
    const TYPE_PRODUCT_BRAND = 3;
    const TYPE_USER = 4;
    const TYPE_USER_ROLE = 5;
    const TYPE_DELIVERY_METHOD = 6;
    const TYPE_PAY_METHOD = 7;
    const TYPE_FIRST_ORDER = 8;
    const TYPE_CART_TOTAL = 9;
    const TYPE_REFERRAL = 10;
    const TYPE_BUNDLE = 11;
    
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
