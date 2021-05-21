<?php

namespace App\Services\Bonus;

use App\Models\Bonus\Bonus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BonusHelper
{
    /**
     * @param array $data
     *
     * @return bool
     */
    public static function validate(array $data)
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

    /**
     * @return bool
     */
    public static function validateRelations(Bonus $bonus)
    {
        $bonus->refresh();
        $data['type'] = $bonus->type;
        $data['offers'] = $bonus->offers;
        $data['brands'] = $bonus->brands;
        $data['categories'] = $bonus->categories;

        switch ($data['type']) {
            case Bonus::TYPE_OFFER:
                return $data['offers']->isNotEmpty()
                    && $data['brands']->isEmpty()
                    && $data['categories']->isEmpty()
                    && $data['offers']->filter(function ($offer) {
                        return $offer['except'];
                    })->isEmpty();
            case Bonus::TYPE_BRAND:
                return $data['offers']->filter(function ($offer) {
                        return !$offer['except'];
                })->isEmpty()
                    && $data['brands']->isNotEmpty()
                    && $data['categories']->isEmpty()
                    && $data['brands']->filter(function ($brand) {
                        return $brand['except'];
                    })->isEmpty();
            case Bonus::TYPE_CATEGORY:
                return $data['offers']->filter(function ($offer) {
                        return !$offer['except'];
                })->isEmpty()
                    && $data['brands']->filter(function ($brand) {
                        return !$brand['except'];
                    })->isEmpty()
                    && $data['categories']->isNotEmpty();
            case Bonus::TYPE_SERVICE:
                return false; // todo
                break;
            case Bonus::TYPE_ANY_OFFER:
            case Bonus::TYPE_ANY_BRAND:
            case Bonus::TYPE_ANY_CATEGORY:
            case Bonus::TYPE_ANY_SERVICE:
            case Bonus::TYPE_CART_TOTAL:
                return $data['offers']->isEmpty() && $data['brands']->isEmpty() && $data['categories']->isEmpty();
            default:
                return false;
        }
    }

    /**
     * @param array $relations
     * @return bool
     */
    public static function updateRelations(Bonus $bonus, array $data)
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
