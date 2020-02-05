<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Discount\Discount;
use App\Core\Discount\DiscountHelper;
use Greensight\CommonMsa\Rest\Controller\CountAction;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class DiscountController
 * @package App\Http\Controllers\V1
 */
class DiscountController extends Controller
{
    use DeleteAction;
    use UpdateAction;
    use ReadAction;
    use CountAction;

    public function create(Request $request, RequestInitiator $client)
    {
        $data = $request->validate([
            'name' => 'string|required',
            'type' => 'numeric|required',
            'value' => 'numeric|required',
            'value_type' => 'numeric|required',
            'start_date' => 'string|nullable',
            'end_date' => 'string|nullable',
            'promo_code_only' => 'boolean|required',
            'status' => 'numeric|required',
            'approval_status' => 'numeric|required',
            'sponsor' => 'numeric|required',
            'merchant_id' => 'numeric|nullable',
            'relations' => 'array|required',
        ]);

        DiscountHelper::validate($data);

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
}
