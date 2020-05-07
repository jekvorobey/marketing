<?php

namespace App\Services\Bonus;

use App\Models\Bonus\Bonus;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BonusHelper
{
    /**
     * @param array $data
     * @return bool
     */
    public static function validate(array $data)
    {
        if (!in_array($data['type'], Bonus::availableTypes())) {
            throw new HttpException(400, 'PromoCode type error');
        }

        if (!in_array($data['status'], Bonus::availableStatuses())) {
            throw new HttpException(400, 'PromoCode status error');
        }

        if ($data['value'] < 0) {
            throw new HttpException(400, 'PromoCode value error');
        }

        if (!in_array($data['value_type'], [Bonus::VALUE_TYPE_PERCENT, Bonus::VALUE_TYPE_RUB])) {
            throw new HttpException(400, 'PromoCode value_type error');
        }

        if ($data['valid_period'] < 0) {
            throw new HttpException(400, 'PromoCode valid_period error');
        }

        if (isset($data['start_date']) && isset($data['end_date'])
            && Carbon::parse($data['start_date'])->gt(Carbon::parse($data['end_date']))) {
            throw new HttpException(400, 'Bonus period error');
        }

        return true;
    }
}
