<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasIncludes;
use App\Http\Traits\HasSearch;
use App\Http\Traits\HasUsers;
use App\Models\Certificate\Card;
use App\Services\Certificate\ActivatingHelper;
use App\Services\Certificate\ActivatingStatus;
use App\Services\Certificate\ReserveHelper;
use App\Services\Certificate\TransactionHelper;
use App\Services\Certificate\TransactionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class CertificateCardController extends Controller
{
    use HasSearch;
    use HasIncludes;
    use HasUsers;

    protected function tapSearchResult($result, Request $request)
    {
        $customer_fields = Arr::only($this->getIncludes($request), ['customer', 'recipient']);

        if (!empty($customer_fields))
            $this->attachCustomerUsers($result, $customer_fields);

        return $result;
    }

    public function read(Request $request): JsonResponse
    {
        return $this->searchResult($request, Card::class);
    }

    public function activateById($id, Request $request): JsonResponse
    {
        $operationCode = ActivatingHelper::activateById(
            $id,
            $request->get('recipient_id')
        );

        $status = new ActivatingStatus($operationCode);

        return response()->json($status->toArray());
    }

    public function activateByPin(Request $request): JsonResponse
    {
        $operationCode = ActivatingHelper::activateByPin(
            $request->get('pin'),
            $request->get('recipient_id')
        );

        $status = new ActivatingStatus($operationCode);

        return response()->json($status->toArray());
    }

    public function pay($id, Request $request): JsonResponse
    {
        $operationCode = TransactionHelper::pay(
            $id,
            $request->get('sum', 0)
        );

        $status = new TransactionStatus($operationCode);

        return response()->json($status->toArray());
    }

    public function reserve(Request $request): JsonResponse
    {
        $status = ReserveHelper::reserve(
            $request->get('customer_id'),
            $request->get('sum', 0)
        );

        return response()->json($status->toArray());
    }

    public function usable($customerId): JsonResponse
    {
        $amount = 0;
        $certificates = [];

        foreach (Card::usableForOrders($customerId)->get() as $card) {
            $certificates[] = [
                'id' => $card->id,
                'code' => $card->name,
                'amount' => $card->balance,
            ];
            $amount += $card->balance;
        }

        return response()->json(compact('amount', 'certificates'));
    }

}
