<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Bonus\ProductBonusOption\ProductBonusOption;

class ProductBonusOptionController extends Controller
{
    /**
     * @param $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function get($productId)
    {
        $productBonusOption = ProductBonusOption::query()->where('product_id', $productId)->first();
        if (!$productBonusOption) {
            return response()->json(null, 404);
        }

        return response()->json($productBonusOption);
    }

    /**
     * @param $productId
     * @param $key
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function value($productId, $key)
    {
        $productBonusOption = ProductBonusOption::query()->where('product_id', $productId)->first();
        if (!$productBonusOption || !array_key_exists($key, $productBonusOption->value)) {
            return response()->json(null, 404);
        }

        return response()->json(['item' => $productBonusOption->value[$key]]);
    }

    /**
     * @param $productId
     * @param $key
     *
     * @return \Illuminate\Http\Response
     */
    public function put($productId, $key)
    {
        $value = request('value');
        $productBonusOption = ProductBonusOption::query()->where('product_id', $productId)->first();
        if (!$productBonusOption) {
            $productBonusOption = new ProductBonusOption([
                'product_id' => $productId,
                'value' => [],
            ]);
        }

        $item = $productBonusOption->value;
        $item[$key] = $value;
        $productBonusOption->value = $item;
        $productBonusOption->save();
        return response('', 204);
    }

    /**
     * @param $productId
     * @param $key
     *
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function delete($productId, $key)
    {
        $productBonusOption = ProductBonusOption::query()->where('product_id', $productId)->first();
        if (!$productBonusOption) {
            return response('', 204);
        }

        $item = $productBonusOption->value;
        unset($item[$key]);
        $productBonusOption->value = $item;
        if (count($productBonusOption->value) === 0) {
            $productBonusOption->delete();
        } else {
            $productBonusOption->save();
        }
        return response('', 204);
    }
}
