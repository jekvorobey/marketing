<?php

namespace app\Models\Discount;

use Illuminate\Database\Eloquent\Model;
/**
* @property int $discount_category_id - ID категории-скидки
* @property int $category_id - ID категории
*/
class DiscountAdditionalCategory extends Model
{
    const FILLABLE = ['category_id'];

    protected $fillable = self::FILLABLE;
}
