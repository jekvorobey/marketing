<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CopyAndDeleteDiscountRequest;
use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\LogicalOperator;
use App\Services\Discount\ChildDiscountService;
use App\Services\Discount\DiscountHelper;
use App\Services\Calculator\Checkout\CheckoutCalculatorBuilder;
use Carbon\Carbon;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class DiscountController
 * @package App\Http\Controllers\V1
 */
class DiscountController extends Controller
{
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
     * @return JsonResponse
     * @throws PimException
     */
    public function count(Request $request): JsonResponse
    {
        $query = Discount::query();
        $total = $this->modifyQuery($request, $query)->count();
        return response()->json(['total' => $total]);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function find($id): JsonResponse
    {
        $discount = Discount::query()
            ->with([
                'offers',
                'bundleItems',
                'brands',
                'categories',
                'segments',
                'roles',
                'conditions',
            ])
            ->where('id', (int) $id)
            ->firstOrFail();

        return response()->json($discount);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws PimException
     */
    public function read(Request $request): JsonResponse
    {
        $id = $request->route('id');
        if ($id > 0) {
            return $this->find($id);
        }

        return response()->json([
            'items' => $this->modifyQuery($request, Discount::query())
                ->orderBy('id', $request->get('sortDirection') === 'asc' ? 'asc' : 'desc')
                ->get(),
        ]);
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function updateStatus(Request $request): Response
    {
        $data = $request->validate([
            'ids' => 'array|required',
            'ids.*' => 'integer|required',
            'status' => 'integer|required',
        ]);

        if (!in_array($data['status'], Discount::availableStatuses())) {
            throw new HttpException(400, 'Status not found');
        }

        /** @var Collection|Discount[] $discounts */
        $discounts = Discount::query()
            ->whereIn('id', $data['ids'])
            ->get();

        if ($discounts->isEmpty()) {
            throw new HttpException(500, 'Status update error');
        }

        foreach ($discounts as $discount) {
            $discount->status = $data['status'];
            $discount->save();
        }

        return response('', 204);
    }

    /**
     * @param CopyAndDeleteDiscountRequest $request
     * @return Response
     * @throws \Throwable
     */
    public function delete(CopyAndDeleteDiscountRequest $request): Response
    {
        DB::transaction(function () use ($request) {
            $r = true;
            $ids = $request->get('ids');
            $discounts = Discount::query()->whereIn('id', $ids)->get();
            foreach ($discounts as $discount) {
                $r &= $discount->delete();
            }

            if (!$r) {
                throw new HttpException(500, 'Status update error');
            }
        });

        return response('', 204);
    }

    /**
     * @param int $id
     * @param Request $request
     * @return Response
     * @throws \Throwable
     */
    public function update(int $id, Request $request): Response
    {
        /** @var Discount $discount */
        $discount = Discount::query()->findOrFail($id);

        $data = $request->validate([
            'name' => 'string',
            'type' => 'numeric',
            'value' => 'numeric',
            'value_type' => 'numeric',
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable',
            'promo_code_only' => 'boolean',
            'max_priority' => 'boolean|sometimes',
            'summarizable_with_all' => 'boolean|sometimes',
            'status' => 'numeric',
            'product_qty_limit' => 'numeric|nullable',
            'merchant_id' => 'numeric|nullable',
            'relations' => 'array',
            'comment' => 'string|nullable',
            'promoCodes' => 'array|required_if:promo_code_only,true',
            'show_on_showcase' => 'boolean|required',
            'showcase_value_type' => 'numeric|required_if:show_on_showcase,true',
            'show_original_price' => 'boolean|required',
            'conditions_logical_operator' => ['numeric', 'nullable', Rule::in(LogicalOperator::all())],
            'child_discounts' => 'array|nullable'
        ]);

        foreach ($data as $field => $value) {
            if (!in_array($field, ['relations', 'promoCodes', 'child_discounts'])) {
                $discount[$field] = $value;
            }
        }

        if ($data['promo_code_only'] && is_array($data['promoCodes'])) {
            $discount->promoCodes()->sync($data['promoCodes']);
        } else {
            $discount->promoCodes()->detach();
        }

        try {
            DiscountHelper::validate($discount->toArray());
        } catch (HttpException $ex) {
            return response($ex->getMessage(), $ex->getStatusCode());
        } catch (\Throwable $ex) {
            return response($ex->getMessage(), 500);
        }

        try {
            DB::beginTransaction();
            if (array_key_exists('relations', $data)) {
                DiscountHelper::updateRelations($discount, $data['relations'] ?? []);
            } else {
                DiscountHelper::validateRelations($discount, []);
            }
            $discount->save();
            $childDiscountService =  new ChildDiscountService();
            $childDiscountService->updateChildDiscounts($discount, $data);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new HttpException(400, $e->getMessage());
        }

        return response('', 204);
    }

    /**
     * @param Request $request
     * @param RequestInitiator $client
     * @return JsonResponse
     * @throws \Throwable
     */
    public function create(Request $request, RequestInitiator $client): JsonResponse
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
                'max_priority' => 'boolean|sometimes',
                'summarizable_with_all' => 'boolean|sometimes',
                'status' => 'numeric|required',
                'product_qty_limit' => 'numeric|nullable',
                'merchant_id' => 'numeric|nullable',
                'relations' => 'array',
                'comment' => 'string|nullable',
                'promoCodes' => 'array|required_if:promo_code_only,true',
                'show_on_showcase' => 'boolean|required',
                'showcase_value_type' => 'numeric|required_if:show_on_showcase,true',
                'show_original_price' => 'boolean|required',
                'conditions_logical_operator' => ['numeric', 'nullable', Rule::in(LogicalOperator::all())],
                'child_discounts' => 'array|nullable',
            ]);

            $data['user_id'] = $client->userId();
            $data['relations'] ??= [];
        } catch (\Throwable $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }

        try {
            DiscountHelper::validate($data);
        } catch (HttpException $ex) {
            return response()->json(['error' => $ex->getMessage()], $ex->getStatusCode());
        } catch (\Throwable $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }

        try {
            DB::beginTransaction();
            $discountId = DiscountHelper::create($data);
            $childDiscountService =  new ChildDiscountService();
            $childDiscountService->createChildDiscounts(Discount::find($discountId), $data);
            DB::commit();
        } catch (\Throwable $e) {
            report($e->getMessage());
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
     * @param CopyAndDeleteDiscountRequest $request
     * @param RequestInitiator $client
     * @return Response
     */
    public function copy(CopyAndDeleteDiscountRequest $request, RequestInitiator $client): Response
    {
        DiscountHelper::copy($request->get('ids'), $client->userId());

        return response('', 204);
    }

    /**
     * Возвращаент IDs авторов создания скидок
     */
    public function getAuthors(Request $request): JsonResponse
    {
        return response()->json($this->authors($request));
    }

    /**
     * Возвращаент IDs инициаторов (спонсоров) скидок
     */
    public function getInitiators(Request $request): JsonResponse
    {
        return response()->json($this->initiators($request));
    }

    /**
     * Возвращаент IDs авторов и инициаторов скидок
     */
    public function getUsers(Request $request): JsonResponse
    {
        return response()->json([
            'authors' => $this->authors($request),
            'initiators' => $this->initiators($request),
        ]);
    }

    /**
     * Возвращает данные о примененных скидках
     * @throws PimException
     */
    public function calculate(Request $request): JsonResponse
    {
        $customer = collect($request->post('customer', []));
        $basketItems = collect($request->post('basketItems', []));
        $deliveries = collect($request->post('deliveries', []));
        $payment = collect($request->post('payment', []));
        $promoCode = $request->post('promoCode', null);
        $bonus = $request->post('bonus', null);

        $result = (new CheckoutCalculatorBuilder())
            ->customer($customer)
            ->basketItems($basketItems)
            ->promoCode($promoCode)
            ->deliveries($deliveries)
            ->payment($payment)
            ->bonus($bonus)
            ->calculate();

        return response()->json($result);
    }

    protected function initiators(Request $request): array
    {
        return $this->modifyQuery($request, Discount::query())
            ->select(['merchant_id'])
            ->distinct()
            ->get()
            ->pluck('merchant_id')
            ->toArray();
    }

    protected function authors(Request $request): array
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
     */
    public function modelClass(): string
    {
        return Discount::class;
    }

    /**
     * @throws PimException
     */
    protected function modifyQuery(Request $request, Builder $query): Builder
    {
        $params['page'] = $request->get('page', null);
        $params['perPage'] = $request->get('perPage', null);
        $relations = $request->get('relations', []);
        foreach ($relations as $relation) {
            switch ($relation) {
                case Discount::DISCOUNT_OFFER_RELATION:
                    $query->with('offers');
                    break;
                case Discount::DISCOUNT_BUNDLE_RELATION:
                    $query->with('bundleItems');
                    break;
                case Discount::DISCOUNT_BRAND_RELATION:
                    $query->with('brands');
                    break;
                case Discount::DISCOUNT_CATEGORY_RELATION:
                    $query->with('categories.additionalCategories');
                    break;
                case Discount::DISCOUNT_SEGMENT_RELATION:
                    $query->with('segments');
                    break;
                case Discount::DISCOUNT_USER_ROLE_RELATION:
                    $query->with('roles');
                    break;
                case Discount::DISCOUNT_CONDITION_RELATION: //deprecated
                    $query->with('conditions');
                    break;
                case Discount::DISCOUNT_CONDITION_GROUP_RELATION:
                    $query->with('conditionGroups.conditions');
                    break;
                case Discount::DISCOUNT_PUBLIC_EVENT_RELATION:
                    $query->with('publicEvents');
                    break;
                case Discount::DISCOUNT_BUNDLE_ID_RELATION:
                    $query->with('bundles');
                    break;
                case Discount::DISCOUNT_PROMO_CODES_RELATION:
                    $query->with('promoCodes');
                    break;
                case Discount::DISCOUNT_CHILD_DISCOUNTS_RELATION:
                    $query->with('childDiscounts', function($query) {
                        $query->with(['brands', 'categories', 'offers']);
                    });
                    break;
            }
        }

        $filter = $request->get('filter', []);

        if ($params['page'] > 0 && $params['perPage'] > 0) {
            $offset = ($params['page'] - 1) * $params['perPage'];
            $query->offset($offset)->limit((int) $params['perPage']);
        }

        foreach ($filter as $key => $value) {
            switch ($key) {
                case 'id':
                case 'merchant_id':
                case 'user_id':
                case 'promo_code_only':
                    if (is_array($value)) {
                        $values = collect($value);
                        $includeNull = $values->filter(function ($v) {
                            return $v <= 0;
                        })->isNotEmpty();
                        $ids = $values->filter(function ($v) {
                            return $v > 0;
                        });
                        if ($ids->isNotEmpty()) {
                            $query->whereIn($key, $ids);
                        }

                        if ($includeNull) {
                            $query->orWhereNull($key);
                        }
                    } else {
                        $query->where($key, (int) $value);
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
                    $query->where(function (Builder $query) use ($value, $filter) {
                        if (is_array($value)) {
                            $query->whereNotIn('status', $value);
                        } else {
                            $query->where('status', '!=', $value);
                        }
                        if (array_key_exists('!status_user_id', $filter)) {
                            $query->orWhere('user_id', $filter['!status_user_id']);
                        }
                    });
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
                        $op = $key === 'start_date' ? '<=' : '>=';
                        $query->where(function ($query) use ($key, $op, $value) {
                            $query->where($key, $op, $value)->orWhereNull($key);
                        });
                    }
                    break;
                case 'indefinitely':
                    $query->whereNull('start_date')->whereNull('end_date');
                    break;
                case 'role_id':
                    $query = $query->forRoleId((int) $value);
                    break;
                case 'relateToMerchant':
                    $this->modifyQueryRelateToMerchant($query, $value);
                    break;
                case 'offer_id':
                    if ($filter['type'] == Discount::DISCOUNT_TYPE_BUNDLE_OFFER) {
                        $query->whereHas('bundleItems', function (Builder $query) use ($value) {
                            if (is_array($value)) {
                                $query->whereIn('item_id', $value);
                            } else {
                                $query->where('item_id', $value);
                            }
                        });
                    }
                    break;
            }
        }

        return $query;
    }

    /**
     * Добавить условие на принадрежность скидки к мерчанту
     * @throws PimException
     */
    protected function modifyQueryRelateToMerchant(Builder $query, $value)
    {
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);

        /** @var ProductService $productService */
        $productService = resolve(ProductService::class);

        $offers = $offerService->offers(
            (new RestQuery())
                ->setFilter('merchant_id', $value)
                ->addFields(OfferDto::entity(), 'id', 'product_id')
        );
        $brandIds = [];
        $categoryIds = [];
        $offerIds = [];
        if ($offers->isNotEmpty()) {
            $offerIds = $offers->pluck('id')->all();
            $products = $productService->products(
                (new RestQuery())
                    ->setFilter('id', $offers->pluck('product_id'))
                    ->addFields(ProductDto::entity(), 'id', 'category_id', 'brand_id')
                    ->include('category.ancestors')
            );

            foreach ($products as $product) {
                if ($product->brand_id) {
                    $brandIds[] = $product->brand_id;
                }
                if ($product->category_id) {
                    $categoryIds[$product->category_id] = $product->category_id;
                    foreach ($product->category->ancestors() as $ancestor) {
                        $categoryIds[$ancestor->id] = $ancestor->id;
                    }
                }
            }
        }

        $query->where(function (Builder $query) use ($value, $offerIds, $brandIds, $categoryIds) {
            $query
                ->orWhere('merchant_id', $value)
                ->orWhere(function (Builder $query) use ($offerIds, $brandIds, $categoryIds) {
                    $query->orWhereNull('merchant_id');
                    if ($offerIds) {
                        $query->orWhereHas('offers', function (Builder $query) use ($offerIds) {
                            $query->whereIn('offer_id', $offerIds)->where('except', 0);
                        });
                    }

                    if ($brandIds) {
                        $query->orWhereHas('brands', function (Builder $query) use ($brandIds) {
                            $query->whereIn('brand_id', $brandIds)->where('except', 0);
                        });
                    }

                    if ($categoryIds) {
                        $query->orWhereHas('categories', function (Builder $query) use ($categoryIds) {
                            $query->whereIn('category_id', $categoryIds);
                        });
                    }
                });
        });

//        $query->where(function (Builder $query) use ($value, $offerIds, $brandIds, $categoryIds) {
//            $query->where('merchant_id', $value);
//
//            if ($offerIds) {
//                $query->orWhereHas('offers', function (Builder $query) use ($offerIds) {
//                    $query->whereIn('offer_id', $offerIds)->where('except', 0);
//                });
//            }
//
//            if ($brandIds) {
//                $query->orWhereHas('brands', function (Builder $query) use ($brandIds) {
//                    $query->whereIn('brand_id', $brandIds)->where('except', 0);
//                });
//            }
//
//            if ($categoryIds) {
//                $query->orWhereHas('categories', function (Builder $query) use ($categoryIds) {
//                    $query->whereIn('category_id', $categoryIds);
//                });
//            }
//
//        });
    }

    /**
     * Вычисление скидки на бандлы
     * @return JsonResponse
     */
    public function bundleDiscountValues(Request $request, RequestInitiator $client)
    {
        $data = $request->validate([
            'ids' => 'array|required',
            'ids.*' => 'integer|required',
        ]);

        $userId = $client->userId();
        // @todo Когда будут реализовываться сегменты пользователей
        $segmentIds = [];
        $roleIds = $client->roles();

        $discounts = Discount::query()
            ->select('id', 'value_type', 'value', 'start_date', 'end_date')
            ->with([
                'roles' => function (Relation $query) {
                    $query->select('id', 'discount_id', 'role_id');
                },
                'segments' => function (Relation $query) {
                    $query->select('id', 'discount_id', 'segment_id');
                },
                'conditions' => function (Relation $query) {
                    $query->select('id', 'discount_id', 'type', 'condition');
                },
            ])
            ->whereIn('id', $data['ids'])
            ->get()
            ->filter(function (Discount $discount) use ($userId, $segmentIds, $roleIds) {
                $customerCondition = $discount->conditions->every(function (DiscountCondition $condition) use ($userId) {
                    return
                        ($condition->type !== DiscountCondition::CUSTOMER) ||
                        (in_array($userId, $condition->condition[DiscountCondition::FIELD_CUSTOMER_IDS]))
                    ;
                });

                $segmentCondition = !($segmentIds && !$discount->segments->isEmpty())
                    || !($discount->segments->pluck('segment_id')->intersect($segmentIds)->isEmpty());

                $roleCondition = !($roleIds && !$discount->roles->isEmpty()) || !($discount->roles->pluck('role_id')->intersect($roleIds)->isEmpty());

                return $customerCondition && $segmentCondition && $roleCondition;
            })
                ->map(function (Discount $discount) {
                    return [
                        'id' => $discount->id,
                        'value_type' => $discount->value_type,
                        'value' => $discount->value,
                    ];
                })
                ->all();

        return response()->json([
            'items' => $discounts,
        ]);
    }
}
