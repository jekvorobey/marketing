<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Basket\Basket;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PriceReactorController extends Controller
{
    /**
     * @throws Exception
     */
    public function calculateBasketPrice(Request $request): JsonResponse
    {
        $basket = Basket::fromRequestData($request->all());
        $basket->addPricesAndBonuses();

        return response()->json($basket);
    }
}
