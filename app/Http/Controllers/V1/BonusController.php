<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Bonus\Bonus;
use App\Services\Bonus\BonusHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BonusController extends Controller
{
    const FAILED_DEPENDENCY_CODE = 424;

    /**
     * @param $id
     *
     * @return JsonResponse
     */
    public function find($id)
    {
        $discount = Bonus::find((int) $id);
        if (!$discount) {
            throw new NotFoundHttpException();
        }

        return response()->json($discount);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function read(Request $request)
    {
        $id = $request->route('id');
        if ($id > 0) {
            return $this->find($id);
        }

        return response()->json([
            'items' => Bonus::query()
                ->orderBy('id', $request->get('sortDirection') === 'asc' ? 'asc' : 'desc')
                ->get()
        ]);
    }

    protected function save(Bonus $bonus)
    {
        $required_rule = $bonus->id ? 'nullable' : 'required';

        $rules = [
            'name' => "string|{$required_rule}",
            'code' => "string|{$required_rule}",
            'status' => "numeric|{$required_rule}",
            'type' => "numeric|{$required_rule}",
            'value' => "numeric|{$required_rule}",
            'value_type' => "numeric|{$required_rule}",
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable',
            'promo_code_only' => 'boolean|{$required_rule}',
        ];

        try {
            $data = $this->validate(request(), $rules);
        } catch (\Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }

        try {
            DB::beginTransaction();
            $bonus->fill($data);
            BonusHelper::validate($bonus->attributesToArray());
            $bonus->save();
            DB::commit();
        } catch (HttpException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new HttpException(500, $e->getMessage());
        }
    }

    public function update($id)
    {
        /** @var Bonus|null $bonus */
        $bonus = Bonus::find($id);
        if (!$bonus) {
            throw new NotFoundHttpException();
        }

        try {
            $this->save($bonus);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return response('', 204);
    }

    public function delete($id)
    {
        /** @var Bonus|null $bonus */
        $bonus = Bonus::find($id);
        if (!$bonus) {
            return response('', 204);
        }

        if ($bonus->promoCodes->isNotEmpty()) {
            return response('', self::FAILED_DEPENDENCY_CODE);
        }

        $bonus->delete();
        return response('', 204);
    }
}
