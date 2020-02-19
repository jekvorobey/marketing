<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Discount\Discount;
use App\Services\Discount\DiscountHelper;
use App\Services\Discount\DiscountCalculator;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
    use UpdateAction;

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
     * @param Request $request
     * @param RequestInitiator $client
     * @return JsonResponse
     */
    public function read(Request $request, RequestInitiator $client)
    {
        $id = $request->route('id');
        if ($id > 0) {
            $discount = Discount::find((int) $id);
            if (!$discount) {
                throw new NotFoundHttpException();
            }

            return response()->json($discount);
        }

        $query = Discount::query();
        $query = $this->modifyQuery($request, $query);
        $items = $query->get();
        return response()->json([
            'items' => $items
        ]);
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
     * Возвращает данные о примененных скидках
     *
     * @param Request $request
     * @param RequestInitiator $client
     * @return JsonResponse
     */
    public function calculate(Request $request, RequestInitiator $client)
    {
        $user = collect($request->post('user', []));
        $offer = collect($request->post('offers', []));
        $promoCode = collect($request->post('promo_code', []));
        $delivery = collect($request->post('delivery', []));
        $payment = collect($request->post('payment', []));
        $basket = collect($request->post('basket', []));
        $calculator = new DiscountCalculator($user, $offer, $promoCode, $delivery, $payment, $basket);
        $result = $calculator->calculate();
        return response()->json($result);
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
     * @param Builder $query
     * @return Builder
     */
    protected function modifyQuery(Request $request, Builder $query)
    {
        $params['page'] = $request->get('page', null);
        $params['perPage'] = $request->get('perPage', null);
        $params['sort'] = $request->get('sort', 'asc');
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
                    $query->where($key, (int)$value);
                    break;
                case 'type':
                case 'status':
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    }
                    break;
                case 'name':
                    $query->where($key, 'like', "%{$value}%");
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

        $query->orderBy('id', $params['sort'] === 'asc' ? 'asc' : 'desc');
        return $query;
    }
}
