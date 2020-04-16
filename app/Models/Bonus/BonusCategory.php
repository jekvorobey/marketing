<?php

namespace App\Models\Bonus;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class BonusCategory
 * @package App\Models\Bonus
 */
class BonusCategory extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['bonus_id', 'category_id', 'except'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class);
    }
}
