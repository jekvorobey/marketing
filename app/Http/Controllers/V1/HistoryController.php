<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\History\History;
use App\Http\Traits\HasIncludes;
use App\Http\Traits\HasSearch;
use App\Http\Traits\HasUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class HistoryController extends Controller
{
    use HasSearch;
    use HasUsers;
    use HasIncludes;

    protected function tapSearchResult($result, Request $request)
    {
        $user_fields = Arr::only($this->getIncludes($request), ['user']);

        if (!empty($user_fields))
            $this->attachUsers($result, $user_fields);

        return $result;
    }

    public function read(Request $request): JsonResponse
    {
        return $this->searchResult($request, History::class);
    }
}
