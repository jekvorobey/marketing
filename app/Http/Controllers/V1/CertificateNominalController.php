<?php

namespace App\Http\Controllers\V1;

use App\Models\Certificate\Nominal;
use Illuminate\Http\Request;

class CertificateNominalController extends AbstractCrudController
{
    protected $modelClass = Nominal::class;

    protected function getValidationRules($model, Request $request): array
    {
        $required_rule = $model->exists ? 'nullable' : 'required';

        $rules = [
            'status' => "numeric|nullable",
            'price' => "numeric|min:100|{$required_rule}",
            'activation_period' => "numeric|min:0|nullable",
            'validity' => "numeric|min:0|nullable",
            'amount' => 'numeric|min:0|nullable',
            'returnable' => 'boolean|nullable',
            'designs' => "array|nullable",
            'designs.*' => "numeric|nullable",
        ];

        if (!$model->exists) {
            $rules['creator_id'] = 'numeric|required';
        }

        return $rules;
    }

    protected function save($model, Request $request): array
    {
        /** @var Nominal $model */

        $data = parent::save($model, $request);

        if (isset($data['designs'])) {
            $model->designs()->sync($data['designs']);
        }

        return $data;
    }
}
