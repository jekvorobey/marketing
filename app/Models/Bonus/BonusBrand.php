<?php

namespace App\Models\Bonus;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Hash;

/**
 * Class BonusBrand
 * @package App\Models\Bonus
 *
 * @property int bonus_id
 * @property int brand_id
 * @property int except
 * @property-read Bonus $bonus
 */
class BonusBrand extends AbstractModel
{
    use Hash;

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['bonus_id', 'brand_id', 'except'];

    /** @var array */
    protected $fillable = self::FILLABLE;

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class);
    }

    protected static function boot()
    {
        parent::boot();

        self::saved(function (self $bonusBrand) {
            $bonusBrand->bonus->updateProducts();
        });

        self::deleted(function (self $bonusBrand) {
            $bonusBrand->bonus->updateProducts();
        });
    }
}
