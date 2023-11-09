<?php

namespace app\Models\Discount;

use Illuminate\Database\Eloquent\Model;

class DiscountAdditionalCategory extends Model
{
    const FILLABLE = ['category_id'];

    protected $fillable = self::FILLABLE;
}
