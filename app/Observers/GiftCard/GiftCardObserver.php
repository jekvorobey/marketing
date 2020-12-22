<?php

namespace App\Observers\GiftCard;

use App\Models\GiftCard\GiftCard;
use App\Models\GiftCard\GiftCardHistory;

class GiftCardObserver
{
    public function created(GiftCard $card)
    {

    }

    public function updated(GiftCard $card)
    {
//        GiftCardHistory::saveEvent(GiftCardHistory::TYPE_UPDATE, $card);

//        if ($order->payment_status == $order->getOriginal('payment_status'))
//            return;
//
//        if ($order->type !== Basket::TYPE_GIFT_CARD)
//            return;
//
//        // Изменена статус оплаты для заказа подарочного сертификата - сообщаем об этом маркетинг модулю
//        try {
//            resolve(GiftCardOrderService::class)->updatePaymentStatus($order->id, $order->payment_status);
//        } catch (\Exception $e) {
//            logger("FAILED: markPaid({$order->id}) / " . $e->getMessage());
//        }
    }
}
