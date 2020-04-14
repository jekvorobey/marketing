<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\PromoCode\PromoCode;
use App\Services\PromoCode\PromoCodeHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PromoCodeController extends Controller
{
    /**
     * @param $id
     *
     * @return JsonResponse
     */
    public function find($id)
    {
        $discount = PromoCode::find((int) $id);
        if (!$discount) {
            throw new NotFoundHttpException();
        }

        return response()->json($discount);
    }

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

    public function create()
    {
        try {
            $promoCode = new PromoCode();
            $this->save($promoCode);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(['id' => $promoCode->id], 201);
    }

    public function update($id)
    {
        /** @var PromoCode|null $promoCode */
        $promoCode = PromoCode::query()->where('id', $id)->first();
        if (!$promoCode) {
            throw new NotFoundHttpException();
        }

        try {
            $this->save($promoCode);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return response('', 204);
    }

    protected function save(PromoCode $promoCode)
    {
        $required_rule = $promoCode->id ? 'nullable' : 'required';

        $rules = [
            'merchant_id' => 'numeric|nullable',
            'owner_id' => 'numeric|nullable',
            'name' => "string|{$required_rule}",
            'code' => "string|{$required_rule}",
            'counter' => 'numeric|nullable',
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable',
            'status' => "numeric|{$required_rule}",
            'type' => "numeric|{$required_rule}",
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
        ];

        if (!$promoCode->exists) {
            $rules['creator_id'] = 'numeric|required';
        }

        try {
            $data = $this->validate(request(), $rules);
        } catch (\Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }


        try {
            DB::beginTransaction();
            $promoCode->fill($data);
            PromoCodeHelper::validate($promoCode->attributesToArray());
            $promoCode->save();
            DB::commit();
        } catch (HttpException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new HttpException(500, $e->getMessage());
        }
    }

    public function delete($id)
    {
        /** @var PromoCode|null $promoCode */
        $promoCode = PromoCode::query()->where('id', $id)->first();
        if (!$promoCode) {
            return response('', 204);
        }

        $promoCode->delete();
        return response('', 204);
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
                case 'discount_id':
                case 'owner_id':
                case 'status':
                    if (is_array($value)) {
                        $values = collect($value);
                        $includeNull = $values->filter(function ($v) { return $v <= 0; })->isNotEmpty();
                        $ids = $values->filter(function ($v) { return $v > 0; });
                        $query->where(function (Builder $query) use ($ids, $key, $includeNull) {
                            if ($ids->isNotEmpty()) {
                                $query->whereIn($key, $ids);
                            }

                            if ($includeNull) {
                                $query->orWhereNull($key);
                            }
                        });
                    } else {
                        $query->where($key, (int)$value);
                    }
                    break;
            }
        }

        return $query;
    }
}
