<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Certificate\Card;
use App\Models\Certificate\Design;
use App\Models\Certificate\Nominal;
use App\Models\Certificate\Order;
use Greensight\Oms\Dto\Payment\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CertificateOrderController extends Controller
{
    public function create(Request $request)
    {
        try {
            $offer = $this->createOrder($request);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json($offer, 201);
    }

    private function createOrder(Request $request)
    {

        $order = new Order();

        $rules = [
            'amount' => "required|int|min:1",
            'nominal_id' => "required|int|exists:gift_card_nominals,id",
            'design_id' => "required|int|exists:gift_card_designs,id",
            'is_anonymous' => "bool|nullable",
            'is_to_self' => "bool|nullable",
            'terms_accepted' => "required|accepted",
            'comment' => 'nullable',
            'from_name' => '',
            'from_email' => 'nullable|email',
            'from_phone' => '',
            'to_name' => '',
            'to_email' => 'nullable|email',
            'to_phone' => '',
            'delivery_time' => "required|string",
            'customer_id' => "required|numeric"
        ];

        try {
            $data = $this->validate($request, $rules);
        } catch (\Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }

        if (isset($data['to_phone']) && (string)$data['to_phone'] !== '') {
            $data['to_phone'] = phone_format($data['to_phone']);
        }

        if (isset($data['from_phone']) && (string)$data['from_phone'] !== '') {
            $data['from_phone'] = phone_format($data['from_phone']);
        }

        // -------------------------------------------------------------------------------------
        // Проверям: номинал существует и используется дизайн, который доступен для номинала.
        // -------------------------------------------------------------------------------------
        /** @var Nominal $nominal */
        $nominal = Nominal::find($data['nominal_id']);
        if (!$nominal)
            throw new BadRequestHttpException("Неизвестный номинал");

        /** @var Design $design */
        $design = $nominal->designs()->where('design_id', $data['design_id'])->first();
        if (!$design)
            throw new BadRequestHttpException("Используемый дизайн не доступен для выбранного номинала");

        // -------------------------------------------------------------------------------------
        $order_data = $data;

        $order_data['qty'] = $data['amount'];
        $order_data['price'] = $data['amount'] * $nominal->price;

        $order->fill($order_data)->save();

        $card_date = [
            'offer_id' => $order->id,
            'nominal_id' => $data['nominal_id'],
            'design_id' => $data['design_id'],
            'status' => Card::STATUS_NEW,
            'customer_id' => $data['customer_id'],
            'price' => $nominal->price,
            'balance' => 0,
            'activate_before' => $nominal->activation_period ? Carbon::now()->addDays($nominal->activation_period) : null
        ];

        for ($i = 0; $i < $data['amount']; $i++)
            $order->cards()->create($card_date);

        return $order;
    }

    public function linkOrder($orderId, Request $request)
    {
        $rules = [
            'order_number' => "required|int",
            'id' => "required|int"
        ];

        try {
            $data = $this->validate($request, $rules);
        } catch (\Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }

        Order::findOrFail($data['id'])->fill([
            'order_id' => $orderId,
            'order_number' => $data['order_number']
        ])->save();

        return response('', 204);
    }

    public function setPaymentStatus($orderId, Request $request)
    {
        Order::findByOrderIdOrFail($orderId)->setPaymentStatus($request->get('status'));

        return response('', 204);
    }

    public function getOrder($orderId)
    {
        $order = Order::findByOrderIdOrFail($orderId);
        return response()->json($order);
    }
}
