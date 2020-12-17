<?php

namespace App\Http\Traits;

use Greensight\CommonMsa\Models\AbstractModel;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HasSearch
{
    use HasPaginated;

    public function searchResult(Request $request, string $modelClass, bool $useRouteId = true): JsonResponse
    {
        $id = (int) $request->route('id');

        $restQuery = new RestQuery($request);

        /**
         * @var AbstractModel $modelClass
         */
        $builder = $modelClass::query();

        if ($useRouteId && $id > 0) {
            $result = $modelClass::modifyQuery($builder->where('id', $id), $restQuery)->firstOrFail();
        } else {
            $result = $this->paginated($request, $modelClass::modifyQuery($builder, $restQuery));
        }

        if (method_exists($this, 'tapSearchResult')) {
            $result = call_user_func([$this, 'tapSearchResult'], $result, $request, $restQuery, $modelClass);
        }

        return response()->json($result);
    }
}
