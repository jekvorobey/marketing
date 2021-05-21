<?php

namespace App\Models\Price;

use Greensight\CommonMsa\Models\AbstractModel;
use Pim\Services\SearchService\SearchService;

/**
 * Класс-модель для сущности "Цена на предложение мерчанта"
 * Class Price
 * @package App\Models\Price
 *
 * @property int $offer_id - id предложения
 * @property double $price - цена
 */
class Price extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = ['offer_id', 'price'];

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var string */
    protected $table = 'prices';

    protected static function boot()
    {
        parent::boot();

        self::created(function (self $price) {
            /** @var SearchService $searchService */
            $searchService = resolve(SearchService::class);
            $searchService->markProductForIndexViaOffer($price->offer_id);
        });

        self::updated(function (self $price) {
            /** @var SearchService $searchService */
            $searchService = resolve(SearchService::class);
            $searchService->markProductForIndexViaOffer($price->offer_id);
        });

        self::deleted(function (self $price) {
            /** @var SearchService $searchService */
            $searchService = resolve(SearchService::class);
            $searchService->markProductForIndexViaOffer($price->offer_id);
        });
    }
}
