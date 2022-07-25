<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Bonus\ProductBonusOption\ProductBonusOption;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductBonusOptionController extends Controller
{
    public function get($productId): JsonResponse
    {
        $productBonusOption = ProductBonusOption::query()->where('product_id', $productId)->first();
        if (!$productBonusOption) {
            return response()->json(null, 404);
        }

        return response()->json($productBonusOption);
    }

    public function value($productId, $key): JsonResponse
    {
        /** @var ProductBonusOption $productBonusOption */
        $productBonusOption = ProductBonusOption::query()->where('product_id', $productId)->first();
        if (!$productBonusOption || !array_key_exists($key, $productBonusOption->value)) {
            return response()->json(null, 404);
        }

        return response()->json(['item' => $productBonusOption->value[$key]]);
    }

    public function put($productId, $key): Response
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
     * @throws Exception
     */
    public function delete($productId, $key): Response
    {
        /** @var ProductBonusOption $productBonusOption */
        $productBonusOption = ProductBonusOption::query()->where('product_id', $productId)->firstOrFail();

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
