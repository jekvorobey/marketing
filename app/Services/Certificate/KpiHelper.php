<?php

namespace App\Services\Certificate;

use App\Models\Certificate\Card;
use App\Models\Certificate\Order;

class KpiHelper
{
    public static function getKpi() : array
    {
        $total = (int) Card::query()
            ->where('status', '>', 0)
            ->sum('price');

        $activated = (int) Card::query()
            ->where('status', '>', 0)
            ->whereNotNull('activated_at')
            ->sum('price');

        $balance = (int) Card::query()
            ->where('status', '>', 0)
            ->whereNotNull('activated_at')
            ->sum('balance');

        $amount = (int) Order::query()->count();

        return [
            'total' => $total,
            'activated' => $activated,
            'used' => $activated - $balance,
            'balance' => $balance,
            'orders_amount' => $amount
        ];
    }
}
