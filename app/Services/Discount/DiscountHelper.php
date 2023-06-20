<?php

namespace App\Services\Discount;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountOffer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Pim\Core\PimException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DiscountHelper
{
    /**
     * @param array $data
     */
    public static function validate(array $data): bool
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

        if (
            isset($data['start_date']) && isset($data['end_date'])
            && Carbon::parse($data['start_date'])->gt(Carbon::parse($data['end_date']))
        ) {
            throw new HttpException(400, 'Discount period error');
        }

        return true;
    }

    /**
     * @param array $relations
     */
    public static function validateRelations(Discount $discount, array $relations): bool
    {
        /** @var Collection $offers */
        $offers = $relations[Discount::DISCOUNT_OFFER_RELATION] ?? $discount->offers;
        /** @var Collection $brands */
        $brands = $relations[Discount::DISCOUNT_BRAND_RELATION] ?? $discount->brands;
        /** @var Collection $categories */
        $categories = $relations[Discount::DISCOUNT_CATEGORY_RELATION] ?? $discount->categories;
        /** @var Collection $publicEvents */
        $publicEvents = $relations[Discount::DISCOUNT_PUBLIC_EVENT_RELATION] ?? $discount->publicEvents;

        return match ($discount->type) {
            Discount::DISCOUNT_TYPE_OFFER => $offers->isNotEmpty()
                && $brands->isEmpty()
                && $categories->isEmpty()
                && $publicEvents->isEmpty()
                && $offers->filter(function (DiscountOffer $offer) {
                    return $offer->except;
                })->isEmpty(),
            Discount::DISCOUNT_TYPE_BRAND => $offers->filter(function (DiscountOffer $offer) {
                    return !$offer->except;
            })->isEmpty()
                && $brands->isNotEmpty()
                && $categories->isEmpty()
                && $publicEvents->isEmpty()
                && $brands->filter(function (DiscountBrand $brand) {
                    return $brand->except;
                })->isEmpty(),
            Discount::DISCOUNT_TYPE_CATEGORY => $offers->filter(function (DiscountOffer $offer) {
                    return !$offer->except;
            })->isEmpty()
                && $brands->filter(function (DiscountBrand $brand) {
                    return !$brand->except;
                })->isEmpty()
                && $publicEvents->isEmpty()
                && $categories->isNotEmpty(),
            Discount::DISCOUNT_TYPE_MASTERCLASS => $offers->isEmpty()
                && $brands->isEmpty()
                && $categories->isEmpty()
                && $publicEvents->isNotEmpty(),
            Discount::DISCOUNT_TYPE_BUNDLE_OFFER, Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS,
            Discount::DISCOUNT_TYPE_ANY_OFFER, Discount::DISCOUNT_TYPE_ANY_BUNDLE, Discount::DISCOUNT_TYPE_ANY_BRAND,
            Discount::DISCOUNT_TYPE_ANY_CATEGORY => true,
            Discount::DISCOUNT_TYPE_ANY_MASTERCLASS, Discount::DISCOUNT_TYPE_DELIVERY, Discount::DISCOUNT_TYPE_CART_TOTAL => $offers->isEmpty()
                && $brands->isEmpty()
                && $categories->isEmpty()
                && $publicEvents->isEmpty(),
            default => false,
        };
    }

    /**
     * @throws PimException
     */
    public static function create(array $data): int
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
        $discount->product_qty_limit = $data['product_qty_limit'] ?? null;
        $discount->promo_code_only = $data['promo_code_only'];
        $discount->max_priority = $data['max_priority'] ?? false;
        $discount->summarizable_with_all = $data['summarizable_with_all'] ?? false;
        $discount->comment = $data['comment'] ?? null;

        $ok = $discount->save();
        if (!$ok) {
            throw new HttpException(500);
        }

        $relations = $data['relations'] ?? [];
        foreach ($discount->getMappingRelations() as $relation => $value) {
            collect($relations[$relation] ?? [])->each(function (array $item) use ($discount, $value) {
                $item['discount_id'] = $discount->id;
                /** @var Model $model */
                $model = new $value['class']($item);
                $model->save();
            });
        }

        $discount->updatePimContents();

        return $discount->id;
    }

    public static function copy(array $ids, int $userId): void
    {
        $discounts = Discount::query()->whereIn('id', $ids)->get();
        $discounts->each(function (Discount $discount) use ($userId) {
            DB::beginTransaction();
            $copyDiscount = $discount->replicate();

            $copyDiscount->name = "Копия {$copyDiscount->name}";
            $copyDiscount->status = Discount::STATUS_CREATED;
            $copyDiscount->user_id = $userId;
            $copyDiscountResult = $copyDiscount->save();

            if (!$copyDiscountResult) {
                DB::rollBack();
                throw new HttpException(500, 'Error when copying discount');
            }

            foreach ($discount->getMappingRelations() as $relationKey => $relation) {
                $relation['items']->each(function ($item) use ($copyDiscount, $relationKey) {
                    $copyRelation = $item->replicate();
                    $copyRelation->discount_id = $copyDiscount->id;
                    $relationSaveOk = $copyRelation->save();

                    if (!$relationSaveOk) {
                        DB::rollBack();
                        throw new HttpException(500, "Error when copying discount relation {$relationKey}");
                    }
                });
            }

            DB::commit();
        });
    }

    public static function updateRelations(Discount $discount, array $relations): bool
    {
        $diffs = collect();
        foreach (Discount::availableRelations() as $relation) {
            $relations[$relation] = collect($relations[$relation] ?? []);
        }

        foreach ($discount->getMappingRelations() as $relation => $value) {
            $diffs->put($relation, $value['class']::hashDiffItems(
                $value['items'],
                $relations[$relation]->transform(function (array $item) use ($discount, $value) {
                    $item['discount_id'] = $discount->id;
                    return new $value['class']($item);
                })
            ));
        }

        $diffs->map(function ($item) {
            return $item['removed'];
        })
              ->map(function (Collection $items) {
                  $items->each(function (Model $model) {
                    $model->delete();
                  });
              });

        $diffs->map(function ($item) {
            return $item['added'];
        })
            ->map(function (Collection $items) {
                $items->each(function (Model $model) {
                    $model->save();
                });
            });

        if (!self::validateRelations($discount, $relations)) {
            throw new HttpException(400, 'The discount relations are corrupted');
        }

        if ($diffs->flatten(2)->isNotEmpty()) {
            $discount->relationsWasRecentlyUpdated = true;
        }

        return true;
    }
}
