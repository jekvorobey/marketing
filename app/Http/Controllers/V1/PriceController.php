<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Discount\DiscountSegment;
use App\Models\Discount\DiscountUserRole;
use App\Models\Price\Price;
use App\Services\Price\CatalogPriceCalculator;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class PriceController
 * @package App\Http\Controllers\V1
 */
class PriceController extends Controller
{
    protected function read(Request $request, RequestInitiator $client)
    {
        try {
            $params = $request->validate([
                'offer_ids' => 'array',
                'offer_ids.*' => 'integer',
                'role_ids' => 'array',
                'segment_id' => 'integer|nullable',
            ]);

            $discountPriceCalculator = new CatalogPriceCalculator($params);
            return response()->json([
                'items' => $discountPriceCalculator->calculate()
            ]);
        } catch (\Exception $ex) {
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
     * @return string
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
     * @param Request $request
     * @return JsonResponse
     */
    public function price(int $offerId, Request $request): JsonResponse
    {
        try {
            $params = $request->validate([
                'role_ids'    => 'array',
                'segment_id'  => 'integer',
            ]);
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }

        $items = (new CatalogPriceCalculator([
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
                'discounts' => $items[0]['discounts'] ?? null,
                'bonus' => $items[0]['bonus'] ?? 0,
            ]
        ]);
    }

    public function catalogCombinations(Request $request)
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
                $segmentKey = $segment ?? "0";
                $roleKey = $role ?? "0";

                $items = (new CatalogPriceCalculator([
                    'offer_ids' => $offerIds,
                    'role_ids' => $role,
                    'segment_id' => $segment,
                ]))->calculate();

                foreach ($items as $item) {
                    $discounts = $item['discounts'] ?? null;
                    if (!$discounts && ($segment || $role)) {
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
                        'discounts' => $item['discounts'] ?? null,
                    ];
                }
            }
        }

        return response()->json($prices);
    }

    /**
     * Установить цену для предложения мерчанта
     * @param  int  $offerId
     * @param Request $request
     * @return Response
     */
    public function setPrice(int $offerId, Request $request): Response
    {
        $price = (float) $request->input('price');

        $ok = true;
        $priceModel = Price::query()
            ->where('offer_id', $offerId)
            ->first();
        if (!$price && !is_null($priceModel)) {
            //Удаляем цену на предложение
            try {
                $ok = $priceModel->delete();
            } catch (\Exception $e) {
                $ok = false;
            }
        } else {
            if (is_null($priceModel)) {
                $priceModel = new Price();
            }
            $priceModel->updateOrCreate(['offer_id' => $offerId], ['price' => $price]);
        }

        if (!$ok) {
            throw new HttpException(500);
        }

        return response('', 204);
    }

    /**
     * Установить цены для для предложений мерчанта
     * На вход должен быть передан массив:
     * 'prices' => [
     *      [
     *          'offer_id' => ...,
     *          'price' => ...,
     *      ],
     *      ...
     * ]
     * @param  Request  $request
     * @return Response
     */
    public function setPrices(Request $request): Response
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
            DB::transaction(function () use ($newPrices) {
                $offerIds = array_keys($newPrices);
                /** @var Collection|Price[] $prices */
                $prices = Price::query()->whereIn('offer_id', $offerIds)->get()->keyBy('offer_id');
                foreach ($offerIds as $offerId) {
                    $price = $prices->has($offerId) ? $prices[$offerId] : new Price();
                    $price->offer_id = $offerId;
                    $price->price = $newPrices[$price->offer_id];
                    $price->save();
                }
            });
        }
        catch (\Exception $e) {
            throw new HttpException(500, $e->getMessage());
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

            $discountPriceCalculator = new CatalogPriceCalculator($params);
            $prices = $discountPriceCalculator->calculate();

            $prices = collect($prices)->keyBy('offer_id')
                ->map(function ($item, $key) {
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
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }
    }
}
