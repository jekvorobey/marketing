<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Price\Price;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class PriceController
 * @package App\Http\Controllers\V1
 */
class PriceController extends Controller
{
    //todo Добавить проверку прав
    
    /**
     * Получить цену на предложение мерчанта
     * @param  int  $offerId - id предложения
     * @return JsonResponse
     */
    public function price(int $offerId): JsonResponse
    {
        $price = Price::query()
            ->where('offer_id', $offerId)
            ->first();
    
        return response()->json([
            'item' => $price->price
        ]);
    }
    
    /**
     * Установить кол-во товара на складе
     * @param  int  $storeId
     * @param  int  $productId
     * @param Request $request
     * @return Response
     */
    public function setPrice(int $offerId, Request $request): Response
    {
        // todo добавить проверку прав
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
}
