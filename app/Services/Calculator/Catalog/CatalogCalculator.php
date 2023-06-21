<?php

namespace App\Services\Calculator\Catalog;

use App\Models\Basket\BasketItem;
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
            $params['basketItems'] = array_map(static function (int $offerId) {
                return new BasketItem($offerId, 1, $offerId, 0, 0, 0);
            }, $params['offer_ids']);
            unset($params['offer_ids']);
        }

        $input = new InputCalculator($params);
        $output = new OutputCalculator();

        parent::__construct($input, $output);
    }

    public function calculate(bool $checkPermissions = true): array
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

        return $this->getFormatBasketItems();
    }

    public function getFormatBasketItems(): array
    {
        return $this->input->basketItems->map(function ($basketItem, $basketItemId) {
            return [
                'id' => $basketItemId,
                'offer_id' => $basketItem['offer_id'],
                'price' => $basketItem['price'],
                'price_base' => $basketItem['price_base'],
                'price_retail' => $basketItem['price_retail'],
                'percent_prof' => $basketItem['percent_prof'],
                'percent_retail' => $basketItem['percent_retail'],
                'cost' => $basketItem['cost'] ?? $basketItem['price'],
                'discounts' => $basketItem['discounts'] ?? null,
                'bonus' => $basketItem['bonus'] ?? 0,
            ];
        })->values()->toArray();
    }
}
