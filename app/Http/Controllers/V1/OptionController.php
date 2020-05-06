<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Option\Option;

class OptionController extends Controller
{
    public function putOption($key)
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

    public function getOption($key)
    {
        /** @var Option $option */
        $option = Option::query()->where('key', $key)->first();
        if (!$option) {
            return response()->json(null, 404);
        }
        return response()->json([
            'value' => $option->value ? $option->value['value'] : null,
        ]);
    }
}
