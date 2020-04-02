<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Discount\Discount;
use App\Models\PromoCode\PromoCode;
use App\Services\PromoCode\PromoCodeHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PromoCodeController extends Controller
{
    public function read(Request $request)
    {
        $id = $request->route('id');
        if ($id > 0) {
            return $this->find($id);
        }

        return response()->json([
            'items' => $this->modifyQuery($request, PromoCode::query())
                ->orderBy('id', $request->get('sortDirection') === 'asc' ? 'asc' : 'desc')
                ->get()
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        try {
            $data = $request->validate([
                'creator_id' => 'numeric|required',
                'merchant_id' => 'numeric|nullable',
                'owner_id' => 'numeric|nullable',
                'name' => 'string|required',
                'code' => 'string|required',
                'counter' => 'numeric|nullable',
                'start_date' => 'date|nullable',
                'end_date' => 'date|nullable',
                'status' => 'numeric|required',
                'type' => 'numeric|required',
                'discount_id' => 'numeric|nullable',
                'gift_id' => 'numeric|nullable',
                'bonus_id' => 'numeric|nullable',
                'conditions' => 'array|nullable',
                'conditions.segments' => 'array|nullable',
                'conditions.segments.*' => 'numeric|nullable',
                'conditions.roles' => 'array|nullable',
                'conditions.roles.*' => 'numeric|nullable',
                'conditions.customers' => 'array|nullable',
                'conditions.customers.*' => 'numeric|nullable',
                'conditions.synergy' => 'array|nullable',
                'conditions.synergy.*' => 'numeric|nullable'
            ]);
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }

        try {
            DB::beginTransaction();
            PromoCodeHelper::validate($data);
            $promoCodeId = PromoCode::create($data);
            DB::commit();
            return response()->json(['id' => $promoCodeId], 201);
        } catch (HttpException $ex) {
            DB::rollBack();
            return response()->json(['error' => $ex->getMessage()], $ex->getStatusCode());
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate()
    {
        return response()->json(['code' => PromoCode::generate()]);
    }

    /**
     * @param Request $request
     * @param Builder $query
     * @return Builder
     */
    protected function modifyQuery(Request $request, Builder $query)
    {
        $params['page'] = $request->get('page', null);
        $params['perPage'] = $request->get('perPage', null);

        $filter = $request->get('filter', []);
        if ($params['page'] > 0 && $params['perPage'] > 0) {
            $offset = ($params['page'] - 1) * $params['perPage'];
            $query->offset($offset)->limit((int)$params['perPage']);
        }

        foreach ($filter as $key => $value) {
            switch ($key) {
                // todo
                case 'id':
                case 'merchant_id':
                    if (is_array($value)) {
                        $values = collect($value);
                        $includeNull = $values->filter(function ($v) { return $v <= 0; })->isNotEmpty();
                        $ids = $values->filter(function ($v) { return $v > 0; });
                        if ($ids->isNotEmpty()) {
                            $query->whereIn($key, $ids);
                        }

                        if ($includeNull) {
                            $query->orWhereNull($key);
                        }
                    } else {
                        $query->where($key, (int)$value);
                    }
                    break;
            }
        }

        return $query;
    }
}
