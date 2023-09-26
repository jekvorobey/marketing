<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Discount\Discount;
use App\Models\PromoCode\PromoCode;
use App\Services\PromoCode\PromoCodeHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Http\Response;

class PromoCodeController extends Controller
{
    public function find($id): JsonResponse
    {
        $discount = PromoCode::query()->findOrFail((int) $id);
        if (!$discount) {
            throw new NotFoundHttpException();
        }

        return response()->json($discount);
    }

    public function read(Request $request): JsonResponse
    {
        $id = $request->route('id');
        if ($id > 0) {
            return $this->find($id);
        }

        return response()->json([
            'items' => $this->modifyQuery($request, PromoCode::query())
                ->orderBy('id', $request->get('sortDirection') === 'asc' ? 'asc' : 'desc')
                ->get(),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->modifyQuery($request, PromoCode::query())->count(),
        ]);
    }

    /**
     * Получить выборку поля, учитывая фильтры
     * Например получить все merchant_id, creator_id по аналогии
     * с методом pluck у Collection
     * @param Request $request
     * @return JsonResponse
     */
    public function pluck(Request $request): JsonResponse
    {
        $this->validate($request, [
            'field' => ['required', 'string', Rule::in(PromoCode::FILLABLE)]
        ]);

        return response()->json([
            'items' => $this->modifyQuery($request, PromoCode::query())
                ->pluck($request->get('field'))
                ->filter()
                ->unique()
                ->values(),
        ]);
    }

    public function create(): JsonResponse
    {
        try {
            $promoCode = new PromoCode();
            $this->save($promoCode);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(['id' => $promoCode->id], 201);
    }

    public function update($id): Response|JsonResponse
    {
        /** @var PromoCode|null $promoCode */
        $promoCode = PromoCode::query()->where('id', $id)->firstOrFail();

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
            'type_of_limit' => 'string|nullable|required_with:counter',
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable',
            'status' => "numeric|{$required_rule}",
            'type' => "numeric|{$required_rule}",
            'discounts' => 'array|nullable',
            'discounts.*' => 'numeric',
            'gift_id' => 'numeric|nullable',
            'bonus_id' => 'numeric|nullable',
            'conditions' => 'array|nullable',
            'conditions.segments' => 'array|nullable',
            'conditions.segments.*' => 'numeric|nullable',
            'conditions.roles' => 'array|nullable',
            'conditions.roles.*' => 'numeric|nullable',
            'conditions.customers' => 'array|nullable',
            'conditions.customers.*' => 'numeric|nullable',
        ];

        if (!$promoCode->exists) {
            $rules['creator_id'] = 'numeric|required';
        }

        try {
            $data = $this->validate(request(), $rules);
        } catch (\Throwable $e) {
            throw new HttpException(400, $e->getMessage());
        }

        try {
            DB::beginTransaction();
            $promoCode->fill($data);
            PromoCodeHelper::validate($promoCode->attributesToArray());
            $promoCode->save();
            if (isset($data['discounts'])) {
                $promoCode->discounts()->sync($data['discounts']);
                $promoCode->updateDiscountsPromocodeOnly();
            }
            DB::commit();
        } catch (HttpException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new HttpException(500, $e->getMessage());
        }
    }

    public function delete($id): Response
    {
        /** @var PromoCode|null $promoCode */
        $promoCode = PromoCode::query()->where('id', $id)->firstOrFail();
        $promoCode->delete();

        return response('', 204);
    }

    public function generate(): JsonResponse
    {
        return response()->json(['code' => PromoCode::generate()]);
    }

    /**
     * Проверяется уникальность промокода по коду
     * TODO: переименовать метод, так как он проверяет валидность кода, а не уникальность (напр. isValid())
     * @throws ValidationException
     */
    public function check(): JsonResponse
    {
        $data = $this->validate(request(), [
            'code' => 'required|string|max:32',
        ]);
        $item = PromoCode::query()
            ->where('code', $data['code'])
            ->where(function ($query) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->whereHas('discounts', function ($query) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->whereHas('discounts', function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->first();
        $status = $item ? 'error' : 'ok';
        Log::info('check', ['status' => $status, 'item' => $item]);

        return response()->json([
            'status' => $status,
        ]);
    }

    /**
     * Проверить уникальность кода
     * @param Request $request
     * @return JsonResponse
     */
    public function isCodeUnique(Request $request)
    {
        $data = $this->validate($request, [
            'code' => 'required|string|max:32',
        ]);

        return response()->json([
            'success' => !PromoCode::where('code', $data['code'])->exists(),
        ]);
    }

    protected function modifyQuery(Request $request, Builder $query): Builder
    {
        $query->with($request->get('with', []));
        $params['page'] = $request->get('page', null);
        $params['perPage'] = $request->get('perPage', null);

        $filter = $request->get('filter', []);
        if ($params['page'] > 0 && $params['perPage'] > 0) {
            $offset = ($params['page'] - 1) * $params['perPage'];
            $query->offset($offset)->limit((int) $params['perPage']);
        }

        foreach ($filter as $key => $value) {
            switch ($key) {
                case 'created_at_from':
                    $query->where('created_at', '>=', $value);
                    break;
                case 'created_at_to':
                    $query->where('created_at', '<=', $value);
                    break;
                case 'validity_period_from':
                    $query->where('start_date', '>=', $value);
                    break;
                case 'validity_period_to':
                    $query->where('end_date', '<=', $value);
                    break;
                case 'is_perpetual':
                    $query->whereNull('start_date')->whereNull('end_date');
                    break;
                case 'sponsor':
                    if ($value === PromoCode::SPONSOR_IBT) {
                        $query->whereNull('merchant_id');
                    } else {
                        $query->whereNotNull('merchant_id');
                    }
                    break;
                case 'name':
                    $query->where('name', 'LIKE', "%$value%");
                    break;
                case 'code':
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    } else {
                        $query->where($key, $value);
                    }
                    break;
                case 'discounts':
                    $query->whereHas('discounts', fn ($q) => $q->whereIn('discounts.id', Arr::wrap($value)));
                    break;
                case 'id':
                case 'type':
                case 'creator_id':
                case 'merchant_id':
                case 'owner_id':
                case 'status':
                    if (is_array($value)) {
                        $values = collect($value);
                        $includeNull = $values->filter(function ($v) {
                            return $v <= 0;
                        })->isNotEmpty();
                        $ids = $values->filter(function ($v) {
                            return $v > 0;
                        });
                        $query->where(function (Builder $query) use ($ids, $key, $includeNull) {
                            if ($ids->isNotEmpty()) {
                                $query->whereIn($key, $ids);
                            }

                            if ($includeNull) {
                                $query->orWhereNull($key);
                            }
                        });
                    } else {
                        $query->where($key, (int) $value);
                    }
                    break;
            }
        }

        return $query;
    }
}
