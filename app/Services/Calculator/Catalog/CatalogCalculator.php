<?php

namespace App\Services\Calculator\Catalog;

use App\Services\Calculator\AbstractCalculator;
use App\Services\Calculator\Bonus\BonusCatalogCalculator;
use App\Services\Calculator\Discount\DiscountCatalogCalculator;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;
use Pim\Core\PimException;

/**
 * Класс для расчета скидок (цен) для отображения в каталоге
 * Class CatalogPriceCalculator
 * @package App\Services\Discount
 */
class CatalogCalculator extends AbstractCalculator
{
    /**
     * DiscountPriceCalculator constructor.
     *
     * @param array|null $params
     *  [
     *  'offer_ids' => int[] – ID офферов
     *  'role_ids' => int[]|null, – Роли пользователя
     *  'segment_id' => int|null, – Сегмент пользователя
     *  ]
     *
     * @throws PimException
     */
    public function __construct(array $params = [])
    {
        if (isset($params['offer_ids'])) {
            $params['offers'] = array_map(function (int $offerId) {
                return ['id' => $offerId];
            }, $params['offer_ids']);
            unset($params['offer_ids']);
        }

        $input = new InputCalculator($params);
        $output = new OutputCalculator();

        parent::__construct($input, $output);
    }

    /**
     * @return array
     */
    public function calculate(bool $checkPermissions = true)
    {
        $calculators = [
            DiscountCatalogCalculator::class,
            BonusCatalogCalculator::class,
        ];

        foreach ($calculators as $calculatorName) {
            /** @var AbstractCalculator $calculator */
            $calculator = new $calculatorName($this->input, $this->output);
            $calculator->calculate($checkPermissions);
        }

        return $this->getFormatOffers();
    }

    /**
     * @return array
     */
    public function getFormatOffers()
    {
        return $this->input->offers->map(function ($offer, $offerId) {
            return [
                'offer_id' => $offerId,
                'price' => $offer['price'],
                'cost' => $offer['cost'] ?? $offer['price'],
                'discounts' => $offer['discounts'] ?? null,
                'bonus' => $offer['bonus'] ?? 0,
            ];
        })->values()->toArray();
    }
}
