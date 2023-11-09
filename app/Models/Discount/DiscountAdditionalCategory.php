<?php

namespace app\Models\Discount;

use Illuminate\Database\Eloquent\Model;
/**
* @property int $discount_category_id - ID категории-скидки
* @property int $category_id - ID категории
* @property bool $except - исключить
*/
class DiscountAdditionalCategory extends Model
{
    const FILLABLE = ['category_id', 'except'];

    protected $fillable = self::FILLABLE;
}
