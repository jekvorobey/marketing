<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

trait HasPaginated
{
    protected function paginated(Request $request, Builder $builder): LengthAwarePaginator
    {
        $pageData = $request->get('page', []);

        $page = intval($pageData['number'] ?? 1);
        $perPage = intval($pageData['size'] ?? 0);
        $page = ($page > 1) ? $page : 1;
        $perPage = ($perPage >= 1 && $perPage <= 1000) ? $perPage : null;

        return $builder->paginate($perPage, '*', '', $page);
    }
}
