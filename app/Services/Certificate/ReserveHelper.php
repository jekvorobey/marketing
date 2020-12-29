<?php

namespace App\Services\Certificate;

use App\Models\Certificate\Card;

class ReserveHelper
{
    public static function reserve($customerId, $summa) : ReserveStatus
    {
        $customerId = (int) $customerId;
        $summa = (int) $summa;

        if ($summa < 0)
            return new ReserveStatus(ReserveStatus::INVALID_SUM);

        if ($summa === 0)
            return new ReserveStatus(ReserveStatus::SUCCESS);

        // Карты выбираем в порядке их даты действия,
        // т.е. в первую очередь списываем с тех, у которых дата действия ближе

        $cards = Card::usableForOrders($customerId)->get();

        $balance = $cards->sum('balance');

        if ($balance < $summa)
            return new ReserveStatus(ReserveStatus::INSUFFICIENT_FUNDS);

        $certificates = [];

        foreach ($cards as $card)
        {
            $used_balance = min($summa, $card->balance);

            $certificates[] = [
                'id' => $card->id,
                'code' => $card->name,
                'amount' => $used_balance,
            ];

            $summa -= $used_balance;

            if ($summa <= 0)
                break;
        }

        return new ReserveStatus(ReserveStatus::SUCCESS, $certificates);
    }
}
