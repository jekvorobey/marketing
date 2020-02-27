<?php

namespace App\Services\Discount;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCategory;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountOffer;
use App\Models\Discount\DiscountSegment;
use App\Models\Discount\DiscountUserRole;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DiscountHelper
{
    /**
     * @param array $data
     * @return bool
     */
    public static function validate(array $data)
    {
        if (!in_array($data['type'], Discount::availableTypes())) {
            throw new HttpException(400, 'Discount type error');
        }

        if (!in_array($data['value_type'], [Discount::DISCOUNT_VALUE_TYPE_RUB, Discount::DISCOUNT_VALUE_TYPE_PERCENT])) {
            throw new HttpException(400, 'Discount value type error');
        }

        if ($data['user_id'] < 0) {
            throw new HttpException(400, 'User ID value error');
        }

        if ($data['value'] < 0) {
            throw new HttpException(400, 'Discount value error');
        }

        if ($data['value_type'] === Discount::DISCOUNT_VALUE_TYPE_PERCENT && $data['value'] > 100) {
            throw new HttpException(400, 'Discount percent value error');
        }

        if (!in_array($data['status'], Discount::availableStatuses())) {
            throw new HttpException(400, 'Discount status error');
        }

        return true;
    }

    /**
     * @param array $data
     * @return int
     */
    public static function create(array $data)
    {
        $discount = new Discount();
        $discount->user_id = $data['user_id'];
        $discount->merchant_id = $data['merchant_id'] ?? null;
        $discount->name = $data['name'];
        $discount->type = $data['type'];
        $discount->value = $data['value'];
        $discount->value_type = $data['value_type'];
        $discount->start_date = $data['start_date'] ?? null;
        $discount->end_date = $data['end_date'] ?? null;
        $discount->status = $data['status'];
        $discount->promo_code_only = $data['promo_code_only'];

        $ok = $discount->save();
        if (!$ok) {
            throw new HttpException(500);
        }

        self::createRelations($data, $discount);
        return $discount->id;
    }

    /**
     * @param $data
     * @param Discount $discount
     * @return bool
     */
    protected static function createRelations($data, Discount $discount)
    {
        $discountId = $discount->id;
        foreach ($data['relations'] as $type => $items) {
            if (empty($items)) {
                continue;
            }

            foreach ($items as $item) {
                switch ($type) {
                    case Discount::DISCOUNT_OFFER_RELATION:
                        $r = new DiscountOffer();
                        $r->discount_id = $discountId;
                        $r->offer_id = $item['offer_id'];
                        $r->except = $item['except'];
                        $r->save();

                        break;
                    case Discount::DISCOUNT_BRAND_RELATION:
                        $r = new DiscountBrand();
                        $r->discount_id = $discountId;
                        $r->brand_id = $item['brand_id'];
                        $r->except = $item['except'];
                        if (!$r->save()) {
                            throw new HttpException(500);
                        }
                        break;
                    case Discount::DISCOUNT_CATEGORY_RELATION:
                        $r = new DiscountCategory();
                        $r->discount_id = $discountId;
                        $r->category_id = $item['category_id'];
                        $r->except = $item['except'];
                        if (!$r->save()) {
                            throw new HttpException(500);
                        }
                        break;
                    case Discount::DISCOUNT_SEGMENT_RELATION:
                        $r = new DiscountSegment();
                        $r->discount_id = $discountId;
                        $r->segment_id = $item['segment_id'];
                        $r->except = $item['except'];
                        if (!$r->save()) {
                            throw new HttpException(500);
                        }
                        break;
                    case Discount::DISCOUNT_USER_ROLE_RELATION:
                        $r = new DiscountUserRole();
                        $r->discount_id = $discountId;
                        $r->role_id = $item['role_id'];
                        $r->except = $item['except'];
                        if (!$r->save()) {
                            throw new HttpException(500);
                        }
                        break;
                    case Discount::DISCOUNT_CONDITION_RELATION:
                        if (isset($item['condition'][DiscountCondition::FIELD_SYNERGY])) {
                            foreach ($item['condition'][DiscountCondition::FIELD_SYNERGY] as $other) {
                                if (!$discount->makeCompatible((int) $other)) {
                                    throw new HttpException(500);
                                }
                            }
                        } else {
                            $r = new DiscountCondition();
                            $r->discount_id = $discountId;
                            $r->type = $item['type'];
                            $r->condition = $item['condition'];
                            if (!$r->save()) {
                                throw new HttpException(500);
                            }
                        }
                        break;
                }
            }
        }

        return true;
    }
}
