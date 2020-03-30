<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pim\Services\SearchService\SearchService;

/**
 * Класс-модель для сущности "Скидка на оффер"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $offer_id
 * @property boolean $except
 * @property-read Discount $discount
 * @mixin \Eloquent
 *
 */
class DiscountOffer extends AbstractModel
{
    use DiscountHash;

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'offer_id', 'except'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    protected static function boot()
    {
        parent::boot();

        self::saved(function (self $discountOffer) {
            $discountOffer->discount->updateProducts();
        });

        self::deleted(function (self $discountOffer) {
            $discountOffer->discount->updateProducts();
        });
    }
}
