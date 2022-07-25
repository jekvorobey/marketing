<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Bonus\Bonus;
use App\Services\Bonus\BonusHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Response;

class BonusController extends Controller
{
    public const FAILED_DEPENDENCY_CODE = 424;

    public function find($id): JsonResponse
    {
        $discount = Bonus::query()->findOrFail((int) $id);

        return response()->json($discount);
    }

    public function read(Request $request): JsonResponse
    {
        $id = $request->route('id');
        if ($id > 0) {
            return $this->find($id);
        }

        return response()->json([
            'items' => Bonus::query()
                ->orderBy('id', $request->get('sortDirection') === 'asc' ? 'asc' : 'desc')
                ->get(),
        ]);
    }

    protected function save(Bonus $bonus)
    {
        $required_rule = $bonus->id ? 'nullable' : 'required';

        $rules = [
            'name' => "string|{$required_rule}",
            'status' => "numeric|{$required_rule}",
            'type' => "numeric|{$required_rule}",
            'value' => "numeric|{$required_rule}",
            'value_type' => "numeric|{$required_rule}",
            'valid_period' => 'numeric|nullable',
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable',
            'promo_code_only' => "boolean|{$required_rule}",
            'offers' => 'array|nullable',
            'offers.offer_id' => 'integer',
            'offers.except' => 'boolean',
            'brands' => 'array|nullable',
            'brands.brand_id' => 'integer',
            'brands.except' => 'boolean',
            'categories' => 'array|nullable',
            'categories.category_id' => 'integer',
            'categories.except' => 'boolean',
        ];

        try {
            $data = $this->validate(request(), $rules);
        } catch (\Throwable $e) {
            throw new HttpException(400, $e->getMessage());
        }

        try {
            DB::beginTransaction();
            $bonus->fill($data);
            BonusHelper::validate($bonus->attributesToArray());
            $bonus->save();

            if (isset($data['offers']) || isset($data['brands']) || isset($data['categories'])) {
                BonusHelper::updateRelations($bonus, $data);
            }
            if (!BonusHelper::validateRelations($bonus)) {
                throw new HttpException(400, 'Bonus relation error');
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

    public function create(): JsonResponse
    {
        try {
            $bonus = new Bonus();
            $this->save($bonus);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(['id' => $bonus->id], 201);
    }

    public function update($id): Response|JsonResponse
    {
        /** @var Bonus|null $bonus */
        $bonus = Bonus::query()->findOrFail($id);

        try {
            $this->save($bonus);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return response('', 204);
    }

    public function delete($id): Response
    {
        /** @var Bonus|null $bonus */
        $bonus = Bonus::query()->findOrFail($id);

        if ($bonus->promoCodes->isNotEmpty()) {
            return response('', self::FAILED_DEPENDENCY_CODE);
        }
        $bonus->delete();

        return response('', 204);
    }
}
