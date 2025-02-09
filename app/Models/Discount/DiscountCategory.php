<?php

namespace App\Models\Discount;

use App\Models\Hash;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Класс-модель для сущности "Скидка на товары категории"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $category_id
 * @property bool $except
 * @property bool $except_additional_categories - исключить дополнительные категории
 *
 * @property-read Discount $discount
 * @property-read Collection|DiscountAdditionalCategory[] $additionalCategories
 */
class DiscountCategory extends AbstractModel
{
    use Hash;

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['discount_id', 'category_id', 'except', 'except_additional_categories'];

    /** @var array */
    protected $fillable = self::FILLABLE;

    protected $casts = [
        'except' => 'bool',
    ];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function additionalCategories(): HasMany
    {
        return $this->hasMany(DiscountAdditionalCategory::class);
    }
}
