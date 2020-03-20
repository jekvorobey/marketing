<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Discount\Discount;
use App\Services\Discount\DiscountCalculatorBuilder;
use App\Services\Discount\DiscountHelper;
use Carbon\Carbon;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class DiscountController
 * @package App\Http\Controllers\V1
 */
class DiscountController extends Controller
{
    use DeleteAction;

    /**
     * Задать права для выполнения стандартных rest действий.
     * Пример: return [ RestAction::$DELETE => 'permission' ];
     * @return array
     */
    public function permissionMap(): array
    {
        return [
            // todo добавить необходимые права
        ];
    }

    /**
     * @param Request $request
     * @param RequestInitiator $client
     * @return JsonResponse
     */
    public function count(Request $request, RequestInitiator $client)
    {
        $query = Discount::query();
        $total = $this->modifyQuery($request, $query)->count();
        return response()->json(['total' => $total]);
    }

    /**
     * @param $id
     *
     * @return JsonResponse
     */
    public function find($id)
    {
        $discount = Discount::find((int) $id);
        if (!$discount) {
            throw new NotFoundHttpException();
        }

        return response()->json($discount);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function read(Request $request)
    {
        $id = $request->route('id');
        if ($id > 0) {
            return $this->find($id);
        }

        return response()->json([
            'items' => $this->modifyQuery($request, Discount::query())
                ->orderBy('id', $request->get('sortDirection') === 'asc' ? 'asc' : 'desc')
                ->get()
        ]);
    }

    /**
     * @param int $id
     * @param Request $request
     * @return Response
     */
    public function update(int $id, Request $request)
    {
        /** @var Discount $discount */
        $discount = Discount::find($id);
        if (!$discount) {
            throw new NotFoundHttpException();
        }

        $data = $request->validate([
            'name' => 'string',
            'type' => 'numeric',
            'value' => 'numeric',
            'value_type' => 'numeric',
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable',
            'promo_code_only' => 'boolean',
            'status' => 'numeric',
            'merchant_id' => 'numeric|nullable',
            'relations' => 'array',
        ]);

        foreach ($data as $field => $value) {
            if ($field != 'relations') {
                $discount[$field] = $value;
            }
        }

        try {
            DiscountHelper::validate($discount->toArray());
        } catch (HttpException $ex) {
            return response($ex->getMessage(), $ex->getStatusCode());
        } catch (\Exception $ex) {
            return response($ex->getMessage(), 500);
        }

        try {
            DB::beginTransaction();
            $discount->save();
            if (array_key_exists('relations', $data)) {
                DiscountHelper::updateRelations($discount, $data['relations'] ?? []);
            } else {
                DiscountHelper::validateRelations($discount, []);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new HttpException(400, $e->getMessage());
        }

        return response('', 204);
    }

    /**
     * @param Request $request
     * @param RequestInitiator $client
     * @return JsonResponse
     */
    public function create(Request $request, RequestInitiator $client)
    {
        try {
            $data = $request->validate([
                'name' => 'string|required',
                'type' => 'numeric|required',
                'value' => 'numeric|required',
                'value_type' => 'numeric|required',
                'start_date' => 'string|nullable',
                'end_date' => 'string|nullable',
                'promo_code_only' => 'boolean|required',
                'status' => 'numeric|required',
                'merchant_id' => 'numeric|nullable',
                'relations' => 'array|required',
            ]);

            $data['user_id'] = $client->userId();
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }

        try {
            DiscountHelper::validate($data);
        } catch (HttpException $ex) {
            return response()->json(['error' => $ex->getMessage()], $ex->getStatusCode());
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }

        try {
            DB::beginTransaction();
            $discountId = DiscountHelper::create($data);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'id' => $discountId,
        ], 201);
    }

    /**
     * Возвращаент IDs авторов создания скидок
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getAuthors(Request $request)
    {
        return response()->json($this->authors($request));
    }

    /**
     * Возвращаент IDs инициаторов (спонсоров) скидок
     * @param Request $request
     *
     * @return array
     */
    public function getInitiators(Request $request)
    {
        return response()->json($this->initiators($request));
    }

    /**
     * Возвращаент IDs авторов и инициаторов скидок
     * @param Request $request
     *
     * @return array
     */
    public function getUsers(Request $request)
    {
        return response()->json([
            'authors' => $this->authors($request),
            'initiators' => $this->initiators($request),
        ]);
    }

    /**
     * Возвращает данные о примененных скидках
     *
     * @param Request $request
     * @param RequestInitiator $client
     * @return JsonResponse
     */
    public function calculate(Request $request, RequestInitiator $client)
    {
        $customer = collect($request->post('customer', []));
        $offers = collect($request->post('offers', []));
        $promoCode = collect($request->post('promo_code', []));
        $deliveries = collect($request->post('deliveries', []));
        $payment = collect($request->post('payment', []));

        $result = (new DiscountCalculatorBuilder())
            ->customer($customer)
            ->offers($offers)
            ->promoCode($promoCode)
            ->deliveries($deliveries)
            ->payment($payment)
            ->calculate();

        return response()->json($result);
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    protected function initiators(Request $request)
    {
        return $this->modifyQuery($request, Discount::query())
            ->select(['merchant_id'])
            ->distinct()
            ->get()
            ->pluck('merchant_id')
            ->toArray();
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    protected function authors(Request $request)
    {
        return $this->modifyQuery($request, Discount::query())
            ->select(['user_id'])
            ->distinct()
            ->get()
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * Получить список полей, которые можно редактировать через стандартные rest действия.
     * Пример return ['name', 'status'];
     * @return array
     */
    protected function writableFieldList(): array
    {
        return Discount::FILLABLE;
    }

    /**
     * Получить класс модели в виде строки
     * Пример: return MyModel::class;
     * @return string
     */
    public function modelClass(): string
    {
        return Discount::class;
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
        $relations = $request->get('relations', []);
        foreach ($relations as $relation) {
            switch ($relation) {
                case Discount::DISCOUNT_OFFER_RELATION:
                    $query->with('offers');
                    break;
                case Discount::DISCOUNT_BRAND_RELATION:
                    $query->with('brands');
                    break;
                case Discount::DISCOUNT_CATEGORY_RELATION:
                    $query->with('categories');
                    break;
                case Discount::DISCOUNT_SEGMENT_RELATION:
                    $query->with('segments');
                    break;
                case Discount::DISCOUNT_USER_ROLE_RELATION:
                    $query->with('roles');
                    break;
                case Discount::DISCOUNT_CONDITION_RELATION:
                    $query->with('conditions');
                    break;
            }
        }

        $filter = $request->get('filter', []);

        if ($params['page'] > 0 && $params['perPage'] > 0) {
            $offset = ($params['page'] - 1) * $params['perPage'];
            $query->offset($offset)->limit((int)$params['perPage']);
        }

        foreach ($filter as $key => $value) {
            switch ($key) {
                case 'id':
                case 'merchant_id':
                case 'user_id':
                case 'promo_code_only':
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
                case 'type':
                case 'status':
                    $value = is_array($value) ? $value : [$value];
                    $query->whereIn($key, $value);
                    break;
                case '!type':
                    $value = is_array($value) ? $value : [$value];
                    $query->whereNotIn('type', $value);
                    break;
                case '!status':
                    if (is_array($value)) {
                        $query->where(function (Builder $query) use ($value) {
                            $query->whereNotIn('status', $value)->orWhere('user_id', $value);
                        });
                    } else {
                        $query->where(function (Builder $query) use ($value) {
                            $query->where('status', '!=', $value)->orWhere('user_id', $value);
                        });
                    }
                    break;
                case 'name':
                    $query->where($key, 'like', "%{$value}%");
                    break;
                case 'created_at':
                    if (isset($value['from'])) {
                        $query->where($key, '>=', Carbon::createFromDate($value['from']));
                    }
                    if (isset($value['to'])) {
                        $query->where($key, '<=', Carbon::createFromDate($value['to'])->endOfDay());
                    }
                    break;
                case 'start_date':
                case 'end_date':
                    if (isset($filter['fix_' . $key]) && $filter['fix_' . $key]) {
                        $query->where($key, $value);
                    } else {
                        $op = ($key === 'start_date') ? '>=' : '<=';
                        $query->where(function ($query) use ($key, $op, $value) {
                            $query->where($key, $op, $value)->orWhereNull($key);
                        });
                    }
                    break;
                case 'role_id':
                    $query = $query->forRoleId((int) $value);
                    break;
            }
        }

        return $query;
    }
}
