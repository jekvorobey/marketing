<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Basket\Basket;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PriceReactorController extends Controller
{
    public function calculateBasketPrice(Request $request)
    {
        $basket = Basket::fromRequestData($request->all());
        try {
            $basket->addPrices();
        } catch (\Exception $e) {
            throw new HttpException(500, $e->getMessage());
        }
        return response()->json($basket);
    }
}
