<?php

namespace App\Services\Bonus;

use App\Models\Bonus\Bonus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BonusHelper
{
    public static function validate(array $data): bool
    {
        if (!in_array($data['type'], Bonus::availableTypes())) {
            throw new HttpException(400, 'Bonus type error');
        }

        if (!in_array($data['status'], Bonus::availableStatuses())) {
            throw new HttpException(400, 'Bonus status error');
        }

        if ($data['value'] < 0) {
            throw new HttpException(400, 'Bonus value error');
        }

        if (!in_array($data['value_type'], [Bonus::VALUE_TYPE_PERCENT, Bonus::VALUE_TYPE_ABSOLUTE])) {
            throw new HttpException(400, 'Bonus value_type error');
        }

        if ($data['valid_period'] < 0) {
            throw new HttpException(400, 'Bonus valid_period error');
        }

        if (
            isset($data['start_date']) && isset($data['end_date'])
            && Carbon::parse($data['start_date'])->gt(Carbon::parse($data['end_date']))
        ) {
            throw new HttpException(400, 'Bonus period error');
        }

        return true;
    }

    public static function validateRelations(Bonus $bonus): bool
    {
        $bonus->refresh();
        $data['type'] = $bonus->type;
        $data['offers'] = $bonus->offers;
        $data['brands'] = $bonus->brands;
        $data['categories'] = $bonus->categories;

        return match ($data['type']) {
            Bonus::TYPE_OFFER => $data['offers']->isNotEmpty()
                && $data['brands']->isEmpty()
                && $data['categories']->isEmpty()
                && $data['offers']->filter(function ($offer) {
                    return $offer['except'];
                })->isEmpty(),
            Bonus::TYPE_BRAND => $data['offers']->filter(function ($offer) {
                    return !$offer['except'];
            })->isEmpty()
                && $data['brands']->isNotEmpty()
                && $data['categories']->isEmpty()
                && $data['brands']->filter(function ($brand) {
                    return $brand['except'];
                })->isEmpty(),
            Bonus::TYPE_CATEGORY => $data['offers']->filter(function ($offer) {
                    return !$offer['except'];
            })->isEmpty()
                && $data['brands']->filter(function ($brand) {
                    return !$brand['except'];
                })->isEmpty()
                && $data['categories']->isNotEmpty(),
            Bonus::TYPE_SERVICE => false,
            Bonus::TYPE_ANY_OFFER, Bonus::TYPE_ANY_BRAND, Bonus::TYPE_ANY_CATEGORY, Bonus::TYPE_ANY_SERVICE,
            Bonus::TYPE_CART_TOTAL => $data['offers']->isEmpty() && $data['brands']->isEmpty() && $data['categories']->isEmpty(),
            default => false,
        };
    }

    public static function updateRelations(Bonus $bonus, array $data): bool
    {
        $diffs = collect();
        foreach ($bonus->getMappingRelations() as $k => $relation) {
            $diffs->put($relation['class'], $relation['class']::hashDiffItems(
                $relation['items'],
                collect($data[$k] ?? [])->transform(function (array $item) use ($bonus, $relation) {
                    $item['bonus_id'] = $bonus->id;
                    return new $relation['class']($item);
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

        return true;
    }
}
