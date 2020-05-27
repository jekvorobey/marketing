<?php

namespace App\Models\Bonus;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Hash;

/**
 * Class BonusOffer
 * @package App\Models\Bonus
 *
 * @property int bonus_id
 * @property int offer_id
 * @property int except
 * @property-read Bonus $bonus
 */
class BonusOffer extends AbstractModel
{
    use Hash;

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['bonus_id', 'offer_id', 'except'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class);
    }

    protected static function boot()
    {
        parent::boot();

        self::saved(function (self $bonusOffer) {
            $bonusOffer->bonus->updateProducts();
        });

        self::deleted(function (self $bonusOffer) {
            $bonusOffer->bonus->updateProducts();
        });
    }
}
