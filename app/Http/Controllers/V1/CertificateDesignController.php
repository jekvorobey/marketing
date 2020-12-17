<?php

namespace App\Http\Controllers\V1;

use App\Models\Certificate\Design;
use Illuminate\Http\Request;

class CertificateDesignController extends AbstractCrudController
{
    protected $modelClass = Design::class;

    protected function getValidationRules($model, Request $request): array
    {
        $required_rule = $model->exists ? 'nullable' : 'required';

        return [
            'name' => "string|{$required_rule}",
            'preview' => "string|{$required_rule}",
            'status' => 'numeric|nullable',
        ];
    }
}
