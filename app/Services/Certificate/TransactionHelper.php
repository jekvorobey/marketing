<?php

namespace App\Services\Certificate;

use App\Models\Certificate\Card;

class TransactionHelper
{
    public static function pay($cardId, $summa) : int
    {
        $id = (int) $cardId;
        $sum = (int) $summa;

        if ($sum === 0)
            return TransactionStatus::EMPTY_SUM;

        $card = ($id) ? Card::find($id) : null;

        if (!$card)
            return TransactionStatus::NOT_FOUND;

        $now = NowHelper::now();

        switch ($card->status)
        {
            case Card::STATUS_NEW:
                return TransactionStatus::NOT_PAID;

            case Card::STATUS_PAID:
            case Card::STATUS_SEND:
            case Card::STATUS_DEACTIVATED:
                return TransactionStatus::INACTIVE;

            case Card::STATUS_COMPLETE:
                if ($summa > 0)
                    return TransactionStatus::INSUFFICIENT_FUNDS;
                break;

            case Card::STATUS_EXPIRED_NOT_ACTIVATED:
            case Card::STATUS_EXPIRED:
                return TransactionStatus::EXPIRED;

            case Card::STATUS_ACTIVATED:
            case Card::STATUS_IN_USE:
                // нужно продолжать
                break;

            default:
                return TransactionStatus::UNEXPECTED;
        }

        // -------------------------------------------------------------------------------------------------------------
        // списание
        // -------------------------------------------------------------------------------------------------------------

        if ($sum > 0)
        {
            if ($sum > $card->balance)
                return TransactionStatus::INSUFFICIENT_FUNDS;

            // установлена дата окончания сертификата и она уже прошла
            if ($card->valid_until && $card->valid_until->lessThan($now)) {
                $card->status = Card::STATUS_EXPIRED;
                $card->save();
                return TransactionStatus::EXPIRED;
            }

            $card->balance -= $sum;
            $card->status = ($card->balance > 0 ) ? Card::STATUS_IN_USE : Card::STATUS_COMPLETE;
            $card->save();

            return TransactionStatus::SUCCESS;
        }

        // -------------------------------------------------------------------------------------------------------------
        // пополнение / возврат
        // -------------------------------------------------------------------------------------------------------------

        $balance = $card->balance - $sum;

        if ($balance > $card->price || $balance < 0)
            return TransactionStatus::LIMIT_EXCEEDED;

        $card->balance = $balance;
        $card->status = ($card->balance > 0 ) ? Card::STATUS_IN_USE : Card::STATUS_COMPLETE;

        if ($card->nominal->validity)
            $card->valid_until = $now->clone()->addDays($card->nominal->validity);

        $card->save();

        return TransactionStatus::SUCCESS;
    }
}
