<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Price\Price;
use App\Services\Discount\DiscountCatalogPrice;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
                'segment_id' => 'integer',
            ]);

            $discountPriceCalculator = new DiscountCatalogPrice($params);
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
     * @param  int  $offerId - id предложения
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

        $items = (new DiscountCatalogPrice([
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
                'discount' => $items[0]['discounts'] ?? null,
            ]
        ]);
    }

    /**
     * Установить цену для предложения мерчанта
     * @param  int  $offerId
     * @param Request $request
     * @return Response
     */
    public function setPrice(int $offerId, Request $request): Response
    {
        // todo Добавить проверку прав
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
}
