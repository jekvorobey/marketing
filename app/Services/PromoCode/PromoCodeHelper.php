<?php

namespace App\Services\PromoCode;

use App\Models\Discount\Discount;
use App\Models\PromoCode\PromoCode;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PromoCodeHelper
{
    /**
     * @param array $data
     * @return bool
     */
    public static function validate(array $data)
    {
        if (!in_array($data['type'], PromoCode::availableTypes())) {
            throw new HttpException(400, 'PromoCode type error');
        }

        if (!in_array($data['status'], PromoCode::availableStatuses())) {
            throw new HttpException(400, 'PromoCode status error');
        }

        if (
            isset($data['start_date']) && isset($data['end_date'])
            && Carbon::parse($data['start_date'])->gt(Carbon::parse($data['end_date']))
        ) {
            throw new HttpException(400, 'PromoCode period error');
        }

        switch ($data['type']) {
            case PromoCode::TYPE_DISCOUNT:
                if (!isset($data['discount_id']) || isset($data['gift_id']) || isset($data['bonus_id'])) {
                    throw new HttpException(400, 'PromoCode type error');
                }
                break;
            case PromoCode::TYPE_DELIVERY:
                if (isset($data['discount_id']) || isset($data['gift_id']) || isset($data['bonus_id'])) {
                    throw new HttpException(400, 'PromoCode type error');
                }
                break;
            case PromoCode::TYPE_GIFT:
                if (isset($data['discount_id']) || !isset($data['gift_id']) || isset($data['bonus_id'])) {
                    throw new HttpException(400, 'PromoCode type error');
                }
                break;
            case PromoCode::TYPE_BONUS:
                if (isset($data['discount_id']) || isset($data['gift_id']) || !isset($data['bonus_id'])) {
                    throw new HttpException(400, 'PromoCode type error');
                }
                break;
        }

        if (preg_match('/^[A-Z0-9a-z]+$/', $data['code']) !== 1) {
            throw new HttpException(400, 'PromoCode code error');
        }

        $builder = PromoCode::query()->where('code', $data['code']);
        if (isset($data['id'])) {
            $builder->where('id', '!=', $data['id']);
        }
        if ($builder->first()) {
            throw new HttpException(400, 'PromoCode duplicate error');
        }

        if (isset($data['counter']) && $data['counter'] <= 0) {
            throw new HttpException(400, 'PromoCode counter error');
        }

        if (isset($data['counter']) && $data['counter'] > 0 && !isset($data['type_of_limit'])) {
            throw new HttpException(400, 'PromoCode type of limit error');
        }

        $discount = $data['discount_id'] ? Discount::find($data['discount_id']) : null;
        if (isset($data['discount_id']) && !$discount) {
            throw new HttpException(400, 'PromoCode discount error');
        }

        if (isset($data['merchant_id'])) {
            if (!in_array($data['type'], PromoCode::availableTypesForMerchant())) {
                throw new HttpException(400, 'PromoCode merchant type error');
            }

            if ($data['discount_id'] && $discount->merchant_id != $data['merchant_id']) {
                throw new HttpException(400, 'PromoCode discount type error');
            }
        }

        return true;
    }
}
