<?php

namespace App\Services\Discount;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCategory;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountConditionGroup;
use App\Models\Discount\DiscountOffer;
use App\Models\Discount\LogicalOperator;
use Carbon\Carbon;
use Greensight\Marketing\Dto\Discount\DiscountCategoryDto;
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
     * @param Discount $discount
     * @param array $relations
     * @return bool
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
            Discount::DISCOUNT_TYPE_BUNDLE_OFFER,
            Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS,
            Discount::DISCOUNT_TYPE_ANY_OFFER,
            Discount::DISCOUNT_TYPE_ANY_BUNDLE,
            Discount::DISCOUNT_TYPE_ANY_BRAND,
            Discount::DISCOUNT_TYPE_ANY_CATEGORY => true,
            Discount::DISCOUNT_TYPE_ANY_MASTERCLASS,
            Discount::DISCOUNT_TYPE_DELIVERY,
            Discount::DISCOUNT_TYPE_CART_TOTAL => $offers->isEmpty()
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
        $discount->promo_code_only = $data['promo_code_only'] ?? false;
        $discount->max_priority = $data['max_priority'] ?? false;
        $discount->summarizable_with_all = $data['summarizable_with_all'] ?? false;
        $discount->comment = $data['comment'] ?? null;
        $discount->conditions_logical_operator = $data['conditions_logical_operator'] ?? LogicalOperator::AND;
        $discount->show_on_showcase = $data['show_on_showcase'] ?? true;
        $discount->showcase_value_type = $data['showcase_value_type'] ?? Discount::DISCOUNT_VALUE_TYPE_PERCENT;
        $discount->show_original_price = $data['show_original_price'] ?? true;
        $discount->except_additional_categories = $data['except_additional_categories'] ?? false;

        $ok = $discount->save();
        if (!$ok) {
            throw new HttpException(500);
        }

        $relations = $data['relations'] ?? [];

        foreach ($discount->getMappingRelations() as $relation => $value) {
            collect($relations[$relation] ?? [])->each(function (array $item) use ($discount, $value, $relation) {
                $item['discount_id'] = $discount->id;
                /** @var Model $model */
                $model = new $value['class']($item);
                $model->save();

                if ($relation === Discount::DISCOUNT_CONDITION_GROUP_RELATION) {
                    self::saveConditions($model, $item['conditions'] ?? []);
                }

                if ($relation === Discount::DISCOUNT_CATEGORY_RELATION) {
                    self::saveAdditionalCategories($model, $item['additionalCategories'] ?? []);
                }
            });
        }

        if ($data['promo_code_only'] && is_array($data['promoCodes'])) {
            $discount->promoCodes()->attach($data['promoCodes']);
        }

        $discount->updatePimContents();

        return $discount->id;
    }

    /**
     * @param Model $conditionGroup
     * @param array $conditions
     * @return void
     */
    public static function saveConditions(Model $conditionGroup, array $conditions): void
    {
        foreach ($conditions as $condition) {
            /** @var DiscountConditionGroup $conditionGroup */
            $condition['discount_id'] = $conditionGroup->discount_id; //TODO: убрать потом, @deprecated
            $conditionGroup->conditions()->create($condition);
        }
    }

    /**
     * @param Model $discountCategory
     * @param array $additionalCategories
     * @return void
     */
    public static function saveAdditionalCategories(Model $discountCategory, array $additionalCategories): void
    {
        foreach ($additionalCategories as $additionalCategory) {
            /** @var DiscountCategory $discountCategory */
            $discountCategory->additionalCategories()->create($additionalCategory);
        }
    }

    /**
     * @param array $ids
     * @param int $userId
     * @return void
     */
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
                if ($relationKey === Discount::DISCOUNT_CONDITION_RELATION) {
                    //TODO: костыль, так как это отношение deprecated, потом убрать
                    continue;
                }
                $relation['items']->each(function ($item) use ($copyDiscount, $relationKey) {
                    $copyRelation = $item->replicate();
                    $copyRelation->discount_id = $copyDiscount->id;
                    $relationSaveOk = $copyRelation->save();

                    if ($relationKey === Discount::DISCOUNT_CONDITION_GROUP_RELATION) {
                        foreach ($item->conditions as $condition) {
                            $copyCondition = $condition->replicate();
                            $copyCondition->discount_condition_group_id = $copyRelation->id;
                            $conditionSaved = $copyCondition->save();

                            if (!$conditionSaved) {
                                DB::rollBack();
                                throw new HttpException(500, "Error when copying discount condition {$relationKey}");
                            }
                        }
                    }

                    if (!$relationSaveOk) {
                        DB::rollBack();
                        throw new HttpException(500, "Error when copying discount relation {$relationKey}");
                    }
                });
            }

            DB::commit();
        });
    }

    /**
     * @param Discount $discount
     * @param array $relations
     * @return bool
     */
    public static function updateRelations(Discount $discount, array $relations): bool
    {
        $diffs = collect();
        $relationsCopy = $relations;

        foreach (Discount::availableRelations() as $relation) {
            $relations[$relation] = collect($relations[$relation] ?? []);
        }

        foreach ($discount->getMappingRelations() as $relation => $value) {
            if ($relation === Discount::DISCOUNT_CONDITION_GROUP_RELATION) {
                continue;
            }

            $diffs->put($relation, $value['class']::hashDiffItems(
                $value['items'],
                $relations[$relation]->transform(function (array $item) use ($discount, $value) {
                    $item['discount_id'] = $discount->id;
                    return new $value['class']($item);
                })
            ));
        }

        $diffs
            ->map(function ($item) {
                return $item['removed'];
            })
            ->map(function (Collection $items) {
                $items->each(function (Model $model) {
                    $model->delete();
                });
            });

        $diffs
            ->map(function ($item) {
                return $item['added'];
            })
            ->map(function (Collection $items) {
                $items->each(function (Model $model) {
                    $model->save();
                });
            });

        $conditionGroupDtos = $relations[Discount::DISCOUNT_CONDITION_GROUP_RELATION] ?? [];
        $discount->conditionGroups()->delete();

        foreach ($conditionGroupDtos as $groupDto) {
            $conditionGroup = new DiscountConditionGroup();
            $conditionGroup->discount_id = $discount->id;
            $conditionGroup->logical_operator = $groupDto['logical_operator'] ?? LogicalOperator::AND;
            $conditionGroup->save();
            self::saveConditions($conditionGroup, $groupDto['conditions'] ?? []);
        }

        $discountCategoryDtos = $relationsCopy[Discount::DISCOUNT_CATEGORY_RELATION] ?? [];
        $discount = $discount->fresh(['categories']);
        // сохранить дополнительные категории
        foreach ($discountCategoryDtos as $discountCategoryDto) {
            $discountCategory = $discount->categories->firstWhere('category_id', $discountCategoryDto['category_id']);

            if ($discountCategory) {
                $discountCategory->additionalCategories()->delete();
                self::saveAdditionalCategories($discountCategory, $discountCategoryDto['additionalCategories']);
            }
        }

        if (!self::validateRelations($discount, $relations)) {
            throw new HttpException(400, 'The discount relations are corrupted');
        }

        if ($diffs->flatten(2)->isNotEmpty()) {
            $discount->relationsWasRecentlyUpdated = true;
        }

        return true;
    }
}
