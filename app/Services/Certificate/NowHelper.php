<?php

namespace App\Services\Certificate;

use Illuminate\Support\Carbon;

class NowHelper
{
    public static function now()
    {
//        return Carbon::now()->addDays(10000);
        return Carbon::now();
    }
}
