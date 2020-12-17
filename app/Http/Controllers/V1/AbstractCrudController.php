<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class AbstractCrudController extends Controller
{
    use HasSearch;

    protected $modelClass;

    public function create(Request $request): JsonResponse
    {
        try {
            $modelClass = $this->modelClass;
            $model = new $modelClass();
            $this->save($model, $request);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(['id' => $model->id], 201);
    }

    public function read(Request $request): JsonResponse
    {
        return $this->searchResult($request, $this->modelClass);
    }

    public function update($id, Request $request)
    {
        $modelClass = $this->modelClass;
        $model = $modelClass::findOrFail((int) $id);
        try {
            $this->save($model, $request);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return response('', 204);
    }

    public function delete($id)
    {
        $modelClass = $this->modelClass;
        $modelClass::findOrFail((int) $id)->delete();
        return response('', 204);
    }

    protected function save($model, Request $request): array
    {
        try {
            $data = $this->validate(request(), $this->getValidationRules($model, $request));
        } catch (\Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }

        $model->fill($data)->save();

        return $data;
    }

    abstract protected function getValidationRules($model, Request $request): array;

}
