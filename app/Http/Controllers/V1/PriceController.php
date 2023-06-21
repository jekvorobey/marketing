<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Discount\DiscountSegment;
use App\Models\Discount\DiscountUserRole;
use App\Models\Price\Price;
use App\Services\Calculator\Catalog\CatalogCalculator;
use App\Services\Price\PriceWriter;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class PriceController
 * @package App\Http\Controllers\V1
 */
class PriceController extends Controller
{
    protected function read(Request $request): JsonResponse
    {
        try {
            $params = $request->validate([
                'offer_ids' => 'array',
                'offer_ids.*' => 'integer',
                'role_ids' => 'array',
                'segment_id' => 'integer|nullable',
            ]);

            $discountPriceCalculator = new CatalogCalculator($params);
            return response()->json([
                'items' => $discountPriceCalculator->calculate(),
            ]);
        } catch (\Throwable $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }
    }

    protected function list(Request $request, PriceWriter $priceWriter): JsonResponse
    {
        try {
            $params = $request->validate([
                'offer_ids' => 'array',
                'offer_ids.*' => 'integer',
            ]);

            $prices = $priceWriter->pricesByOffers($params['offer_ids']);

            return response()->json([
                'items' => $prices,
            ]);
        } catch (\Throwable $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }
    }

    /**
     * Получить список полей, которые можно редактировать через стандартные rest действия.
     * Пример return ['name', 'status'];
     * @return array
     */
    protected function writableFieldList(): array
    {
        return Price::FILLABLE;
    }

    /**
     * Получить класс модели в виде строки
     * Пример: return MyModel::class;
     */
    public function modelClass(): string
    {
        return Price::class;
    }

    /**
     * Задать права для выполнения стандартных rest действий.
     * Пример: return [ RestAction::$DELETE => 'permission' ];
     * @return array
     */
    public function permissionMap(): array
    {
        return [
            // todo добавить необходимые права
        ];
    }

    /**
     * Получить цену на предложение мерчанта
     * @param int $offerId - id предложения
     * @throws PimException
     */
    public function price(int $offerId, Request $request): JsonResponse
    {
        try {
            $params = $request->validate([
                'role_ids' => 'array',
                'segment_id' => 'integer',
            ]);
        } catch (\Throwable $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }

        $items = (new CatalogCalculator([
            'offer_ids' => [$offerId],
            'role_ids' => $params['role_ids'] ?? null,
            'segment_id' => $params['segment_id'] ?? null,
        ]))->calculate();

        if (count($items) === 0) {
            return response()->json(null, 404);
        }

        return response()->json([
            'item' => [
                'offer_id' => $offerId,
                'cost' => $items[0]['cost'],
                'price' => $items[0]['price'],
                'price_base' => $items[0]['price_base'],
                'price_retail' => $items[0]['price_retail'],
                'percent_prof' => $items[0]['percent_prof'],
                'percent_retail' => $items[0]['percent_retail'],
                'discounts' => $items[0]['discounts'] ?? null,
                'bonus' => $items[0]['bonus'] ?? 0,
            ],
        ]);
    }

    /**
     * @throws PimException
     */
    public function catalogCombinations(Request $request): JsonResponse
    {
        $offerIds = $request->get('offer_ids');
        if (!$offerIds) {
            throw new BadRequestHttpException('offer_ids is required');
        }

        $segments = DiscountSegment::query()->select(['id', 'segment_id'])->get()->pluck('segment_id')->unique()->all();
        $segments[] = null;
        $roles = DiscountUserRole::query()->select(['id', 'role_id'])->get()->pluck('role_id')->unique()->all();
        $roles[] = null;

        $prices = [];
        foreach ($segments as $segment) {
            foreach ($roles as $role) {
                $segmentKey = $segment ?? '0';
                $roleKey = $role ?? '0';

                $items = (new CatalogCalculator([
                    'offer_ids' => $offerIds,
                    'role_ids' => [$role],
                    'segment_id' => $segment,
                ]))->calculate(false);

                foreach ($items as $item) {
                    $discounts = $item['discounts'] ?? null;
                    $bonus = $item['bonus'] ?? null;
                    if (!$discounts && !$bonus && ($segment || $role)) {
                        continue;
                    }
                    $offerId = $item['offer_id'];
                    if (!isset($prices[$offerId])) {
                        $prices[$offerId] = [];
                    }

                    if (!isset($prices[$offerId][$segmentKey])) {
                        $prices[$offerId][$segmentKey] = [];
                    }

                    $prices[$offerId][$segmentKey][$roleKey] = [
                        'cost' => $item['cost'],
                        'price' => $item['price'],
                        'price_base' => $item['price_base'],
                        'price_retail' => $item['price_retail'],
                        'percent_prof' => $item['percent_prof'],
                        'percent_retail' => $item['percent_retail'],
                        'bonus' => $item['bonus'],
                        'discounts' => $item['discounts'] ?? null,
                    ];
                }
            }
        }

        return response()->json($prices);
    }

    /**
     * Установить цену для предложения мерчанта
     * @throws PimException
     */
    public function setPrice(int $offerId, Request $request, PriceWriter $priceWriter): Response
    {
        $price = (float) $request->input('price');
        $nullable = (bool) $request->input('nullable');

        $priceWriter->setPrices([$offerId => $price], $nullable);

        return response('', 204);
    }

    /**
     * Установить цены для предложений мерчанта
     * На вход должен быть передан массив:
     * 'prices' => [
     *      [
     *          'offer_id' => ...,
     *          'price' => ...,
     *      ],
     *      ...
     * ]
     */
    public function setPrices(Request $request, PriceWriter $priceWriter): Response
    {
        $data = $request->validate([
            'prices' => 'required|array',
            'prices.*.offer_id' => 'required|integer',
            'prices.*.price' => 'required|numeric',
        ]);
        $newPrices = array_combine(
            array_column($data['prices'], 'offer_id'),
            array_column($data['prices'], 'price')
        );

        try {
            DB::transaction(fn() => $priceWriter->setPrices($newPrices));
        } catch (\Throwable $e) {
            throw new HttpException(500, $e->getMessage());
        }

        return response('', 204);
    }

    /**
     * Удалить цену при удалении самого оффера, не цепляя хуки индексации товара
     * @return mixed
     */
    public function deletePriceByOffer(int $offerId)
    {
        /** @var Price $price */
        $price = Price::query()->where('offer_id', $offerId)->firstOrFail();
        $price->delete();

        return response('', 204);
    }

    /**
     * Пересчитать все цены на товары мерчанта
     * @return mixed
     */
    public function updatePriceByMerchant(int $merchantId, PriceWriter $priceWriter)
    {
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);

        $offersId = $offerService->offers(
            (new RestQuery())
                ->setFilter('merchant_id', $merchantId)
                ->addFields(OfferDto::entity(), 'id')
        )->pluck('id')->toArray();

        /** @var Collection|Price[] $prices */
        $prices = Price::select(
            'id',
            'offer_id',
            'merchant_id',
            'price',
            'price_base',
            'price_retail',
            'percent_prof',
            'percent_retail'
        )->whereIn('offer_id', $offersId)->get();

        $newPrices = [];

        foreach ($prices as $price) {
            if (!$price->price_base
                || !$price->price_retail
                || !$price->merchant_id
            ) {
                if (!$price->price_base && $price->price) {
                    $price->price_base = $price->price;
                }
                if (!$price->price_retail && $price->price) {
                    $price->price_retail = $price->price;
                }
                if (!$price->merchant_id) {
                    $price->merchant_id = $merchantId;
                }

                $price->save();
            }

            //if ($price->offer_id === 2898) {
                if ($price->price_base) {
                    $newPrices[$price->offer_id] = $price->price_base;
                }
            //}
        }

        if ($newPrices) {
            try {
                $priceWriter->setPrices($newPrices);
            } catch (PimException) {
            }
        }

        return response('', 204);
    }

    public function offersIdsByPricesConditionsAndOffer(Request $request): JsonResponse
    {
        try {
            $params = $request->validate([
                'offer_ids' => 'array',
                'offer_ids.*' => 'integer',
                'role_ids' => 'array',
                'segment_id' => 'integer|nullable',
                'price_from' => 'integer',
                'price_to' => 'integer',
            ]);

            $discountPriceCalculator = new CatalogCalculator($params);
            $prices = $discountPriceCalculator->calculate();

            $prices = collect($prices)->keyBy('offer_id')
                ->map(function ($item) {
                    return $item['price'];
                })
                ->all();

            if (array_key_exists('price_from', $params)) {
                $priceFrom = $params['price_from'];
                $prices = array_filter($prices, function ($price) use ($priceFrom) {
                    return $price >= $priceFrom;
                });
            }
            if (array_key_exists('price_to', $params)) {
                $priceTo = $params['price_to'];
                $prices = array_filter($prices, function ($price) use ($priceTo) {
                    return $price <= $priceTo;
                });
            }

            return response()->json([
                'offer_ids' => array_keys($prices),
            ]);
        } catch (\Throwable $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }
    }
}
