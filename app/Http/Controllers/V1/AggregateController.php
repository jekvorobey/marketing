<?php


namespace App\Http\Controllers\V1;


use App\Http\Controllers\Controller;
use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Models\PromoCode\PromoCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\Response;

class AggregateController extends Controller
{
    /**
     * Получить личную скидку в процентах, которая применяется ко всем товарам
     * (нужно для РП, чтобы вывести ему эту информацию в ЛК и в Админке)
     * @param $customer_id
     * @return Response
     */
    public function getPersonalGlobalPercent(int $customer_id)
    {
        /** @var PromoCode $promoCode */
        $promoCode = PromoCode::query()
            ->active()
            ->where('type', PromoCode::TYPE_DISCOUNT)
            ->whereJsonContains('conditions->' . PromoCode::CONDITION_TYPE_CUSTOMER_IDS, $customer_id)
            ->whereJsonLength('conditions', 1)
            ->whereJsonLength('conditions->' . PromoCode::CONDITION_TYPE_CUSTOMER_IDS, 1)
            ->whereHas('discount', function(Builder $query) {
                $query
                    ->where('value_type', Discount::DISCOUNT_VALUE_TYPE_PERCENT)
                    ->where('type', Discount::DISCOUNT_TYPE_CART_TOTAL)
                    ->whereDoesntHave('conditions')
                    ->whereDoesntHave('segments')
                    ->whereDoesntHave('roles')
                    ->active();
            })
            ->with('discount')
            ->first();

        if ($promoCode) {
            return response()->json([
                'promoCode' => $promoCode->code,
                'percent' => $promoCode->discount->value,
            ]);
        }

        /** @var Collection|Discount[] $discounts */
        $discounts = Discount::query()
            ->where('value_type', Discount::DISCOUNT_VALUE_TYPE_PERCENT)
            ->where('type', Discount::DISCOUNT_TYPE_CART_TOTAL)
            ->whereDoesntHave('segments')
            ->whereDoesntHave('roles')
            ->whereHas('conditions', function(Builder $query) use ($customer_id) {
                $query
                    ->where('type', DiscountCondition::CUSTOMER)
                    ->whereJsonContains('condition->' . DiscountCondition::FIELD_CUSTOMER_IDS, $customer_id)
                    ->whereJsonLength('condition', 1);
            })
            ->whereDoesntHave('conditions', function(Builder $query) {
                $query->where('type', '!=', DiscountCondition::CUSTOMER);
            })
            ->with('conditions')
            ->active()
            ->get();

        foreach ($discounts as $discount) {
            if ($discount->conditions->count() == 1) {
                return response()->json([
                    'promoCode' => null,
                    'percent' => $discount->value,
                ]);
            }
        }

        return response()->json([
            'promoCode' => null,
            'percent' => null,
        ]);
    }
}
