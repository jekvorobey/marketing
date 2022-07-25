<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Option\Option;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class OptionController extends Controller
{
    public function putOption($key): Response
    {
        $value = request('value');

        /** @var Option $option */
        $option = Option::query()->where('key', $key)->first();
        if (!$option) {
            $option = new Option();
            $option->key = $key;
        }
        $option->value = ['value' => $value];
        $option->save();

        return response('', 204);
    }

    public function getOption($key): JsonResponse
    {
        /** @var Option $option */
        $option = Option::query()->where('key', $key)->firstOrFail();

        return response()->json([
            'value' => $option->value ? $option->value['value'] : null,
        ]);
    }
}
