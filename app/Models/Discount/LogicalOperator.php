<?php

namespace App\Models\Discount;

class LogicalOperator
{
    public const NO = 1;
    public const AND = 2;
    public const OR = 3;
    public const AND_NO = 4;
    public const OR_NO = 5;

    /**
     * @return int[]
     */
    public static function all(): array
    {
        return [
            self::NO,
            self::AND,
            self::OR,
            self::AND_NO,
            self::OR_NO,
        ];
    }
}
