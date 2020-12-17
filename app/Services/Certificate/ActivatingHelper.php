<?php

namespace App\Services\Certificate;

use App\Models\Certificate\Card;

class ActivatingHelper
{
//    private static function activate($cardId, $pin, $recipientId) : int
    private static function activate(Card $card, $recipientId) : int
    {
        $recipientId = (int) $recipientId;

        if (!$recipientId)
            return ActivatingStatus::UNKNOWN_RECIPIENT;

        $now = NowHelper::now();

        // Установлена дата до которой нужно активировать, и она прошла
        if ($card->activate_before && $card->activate_before->lessThan($now)) {
            $card->status = Card::STATUS_EXPIRED_NOT_ACTIVATED;
            $card->save();
            return ActivatingStatus::EXPIRED;
        }

        $card->activate_before = null;
        $card->activated_at = $now;

        // Если в номинале указан срок дейстивя сертификата после активации,
        // то устанвливаем дату срок действия этого сертификата
        $card->valid_until = ($card->nominal->validity)
            ? $now->clone()->addDays($card->nominal->validity)
            : null;

        // ПИН больше не действует
        $card->pin = null;

        // Сертификат становится активированным
        $card->status = Card::STATUS_ACTIVATED;

        // начисляем теперь на баланс цену сертификата
        $card->balance = $card->price;

        // Выставляем теперь владельца сертификата
        $card->recipient_id = $recipientId;
        $card->save();

        return ActivatingStatus::SUCCESS;
    }

    public static function activateById($id, $recipientId) : int
    {
        /** @var Card $card */
        $card = Card::query()->where('id', (int) $id)->first();

        if (!$card || !$card->status)
            return ActivatingStatus::NOT_FOUND;

        return self::activate($card, $recipientId);
    }

    public static function activateByPin($pin, $recipientId) : int
    {
        $pin = trim($pin);

        if (strlen($pin) !== 8)
            return ActivatingStatus::INVALID_PIN;

        /** @var Card $card */
        $card = Card::query()->where('pin', $pin)->first();

        if (!$card || !$card->status)
            return ActivatingStatus::NOT_FOUND;

        return self::activate($card, $recipientId);
    }

}
