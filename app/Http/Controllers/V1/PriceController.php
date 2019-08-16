<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Price\Price;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
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
    use ReadAction;
    
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
    public function price(int $offerId): JsonResponse
    {
        //todo Добавить проверку прав
        $price = Price::query()
            ->where('offer_id', $offerId)
            ->first();
    
        return response()->json([
            'item' => $price
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
}
