<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CopyAndDeleteDiscountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'array|required',
            'ids.*' => 'integer|required',
        ];
    }
}
