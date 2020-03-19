<?php

namespace App\Services\Discount;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCategory;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountOffer;
use App\Models\Discount\DiscountSegment;
use App\Models\Discount\DiscountUserRole;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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

        if (isset($data['start_date']) && isset($data['end_date'])
            && Carbon::parse($data['start_date'])->gt(Carbon::parse($data['end_date']))) {
            throw new HttpException(400, 'Discount period error');
        }

        return true;
    }

    /**
     * @param Discount $discount
     * @return bool
     */
    public static function validateRelations(Discount $discount)
    {
        /** @var Collection $offers */
        $offers = $discount->offers;
        /** @var Collection $brands */
        $brands = $discount->brands;
        /** @var Collection $categories */
        $categories = $discount->categories;

        switch ($discount->type) {
            case Discount::DISCOUNT_TYPE_OFFER:
                return $offers->isNotEmpty()
                    && $brands->isEmpty()
                    && $categories->isEmpty()
                    && $offers->filter(function (DiscountOffer $offer) { return $offer->except; })->isEmpty();
            case Discount::DISCOUNT_TYPE_BRAND:
                return $offers->filter(function (DiscountOffer $offer) { return !$offer->except; })->isEmpty()
                    && $brands->isNotEmpty()
                    && $categories->isEmpty()
                    && $brands->filter(function (DiscountBrand $brand) { return $brand->except; })->isEmpty();
            case Discount::DISCOUNT_TYPE_CATEGORY:
                return $offers->filter(function (DiscountOffer $offer) { return !$offer->except; })->isEmpty()
                    && $brands->filter(function (DiscountBrand $brand) { return !$brand->except; })->isEmpty()
                    && $categories->isNotEmpty();
            case Discount::DISCOUNT_TYPE_BUNDLE:
                return true; // todo
                break;
            case Discount::DISCOUNT_TYPE_DELIVERY:
            case Discount::DISCOUNT_TYPE_CART_TOTAL:
                return $offers->isEmpty() && $brands->isEmpty() && $categories->isEmpty();
            default:
                return false;
        }
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

        self::createRelations($data['relations'] ?? [], $discount);
        return $discount->id;
    }

    /**
     * @param Discount $discount
     * @param array $relationTypes
     * @return bool
     */
    public static function removeRelations(Discount $discount, array $relationTypes)
    {
        foreach ($relationTypes as $relationType) {
            switch ($relationType) {
                case Discount::DISCOUNT_OFFER_RELATION:
                    $discount->offers()->forceDelete();
                    break;
                case Discount::DISCOUNT_BRAND_RELATION:
                    $discount->brands()->forceDelete();
                    break;
                case Discount::DISCOUNT_CATEGORY_RELATION:
                    $discount->categories()->forceDelete();
                    break;
                case Discount::DISCOUNT_USER_ROLE_RELATION:
                    $discount->roles()->forceDelete();
                    break;
                case Discount::DISCOUNT_SEGMENT_RELATION:
                    $discount->segments()->forceDelete();
                    break;
                case Discount::DISCOUNT_CONDITION_RELATION:
                    $discount->conditions()->forceDelete();
                    break;
                default:
                    return false;
            }
        }

        return true;
    }

    /**
     * @param Discount $discount
     * @param array $relations
     * @return bool
     */
    public static function updateRelations(Discount $discount, array $relations)
    {
        DiscountHelper::removeRelations($discount, [
            Discount::DISCOUNT_OFFER_RELATION,
            Discount::DISCOUNT_BRAND_RELATION,
            Discount::DISCOUNT_CATEGORY_RELATION,
            Discount::DISCOUNT_CONDITION_RELATION,
            Discount::DISCOUNT_USER_ROLE_RELATION,
            Discount::DISCOUNT_SEGMENT_RELATION,
        ]);

        if (!empty($relations)) {
            DiscountHelper::createRelations($relations, $discount);
        }

        if (!DiscountHelper::validateRelations($discount)) {
            throw new HttpException(400, 'The discount relations are corrupted');
        }

        return true;
    }

    /**
     * @param array $relations
     * @param Discount $discount
     * @return bool
     */
    public static function createRelations(array $relations, Discount $discount)
    {
        $discountId = $discount->id;
        foreach ($relations as $type => $items) {
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
                        if (!$r->save()) {
                            throw new HttpException(500);
                        }
                        break;
                    case Discount::DISCOUNT_SEGMENT_RELATION:
                        $r = new DiscountSegment();
                        $r->discount_id = $discountId;
                        $r->segment_id = $item['segment_id'];
                        if (!$r->save()) {
                            throw new HttpException(500);
                        }
                        break;
                    case Discount::DISCOUNT_USER_ROLE_RELATION:
                        $r = new DiscountUserRole();
                        $r->discount_id = $discountId;
                        $r->role_id = $item['role_id'];
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
