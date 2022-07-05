<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Hash;
use Illuminate\Support\Collection;

/**
 * Класс-модель для сущности "Элемент бандла"
 * App\Models\Discount\BundleItem
 *
 * @property int $discount_id
 * @property int $item_id
 * @property-read Discount $discount
 */
class BundleItem extends AbstractModel
{
    use Hash;

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['discount_id', 'item_id'];

    /** @var array */
    protected $fillable = self::FILLABLE;

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    /**
     * Товары в бандле могут повторяться, в отличие от остальных связей скидки
     */
    public static function hashDiff(Collection $a, Collection $b)
    {
        $bHashes = $b->map(fn(self $item) => $item->getHash());

        if ($bHashes->isEmpty()) {
            return $a;
        }

        return $a->filter(function (self $item) use ($bHashes) {
            $key = $bHashes->search($item->getHash());
            $contains = $key !== false;

            if ($contains) {
                $bHashes->forget($key);
            }

            return !$contains;
        });
    }
}
