<?php

namespace App\Models\Bonus;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class BonusBrand
 * @package App\Models\Bonus
 */
class BonusBrand extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['bonus_id', 'brand_id', 'except'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class);
    }
}
