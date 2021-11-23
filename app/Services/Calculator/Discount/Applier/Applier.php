<?php

namespace App\Services\Calculator\Discount\Applier;

use App\Models\Discount\Discount;

interface Applier
{
    public function apply(Discount $discount): ?float;
}
