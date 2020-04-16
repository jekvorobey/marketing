<?php

namespace App\Models\Bonus;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class BonusOffer
 * @package App\Models\Bonus
 */
class BonusOffer extends AbstractModel
{
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
}
