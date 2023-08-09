<?php

namespace App\Models\Price;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Класс-модель для сущности "Цена на предложение мерчанта"
 * Class Price
 * @package App\Models\Price
 *
 * @property int $offer_id - id предложения
 * @property int $merchant_id - id мерчанта
 * @property double $price - цена проф
 * @property double $price_base - цена базовая
 * @property double $price_retail - цена розничная
 * @property float $percent_prof - Значение наценки на цену для проффесионалов
 * @property float $percent_retail - Значение наценки на розничную цену
 * @property string $updated_at - Дата и время последнего обновления
 * @property Collection $pricesByRoles - Цены с привязкой к ролям
 */
class Price extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['merchant_id', 'offer_id', 'price', 'price_base', 'price_retail', 'percent_prof', 'percent_retail'];

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var string */
    protected $table = 'prices';

    public function pricesByRoles(): HasMany
    {
        return $this->hasMany(PriceByRole::class);
    }
}
