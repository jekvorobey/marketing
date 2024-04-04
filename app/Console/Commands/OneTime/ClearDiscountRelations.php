<?php

namespace App\Console\Commands\OneTime;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountConditionGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ClearDiscountRelations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onetime:clear-discount-relations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Удалить отсутствующие скидки из условий суммирования скидок';

    public function handle()
    {
        $this->clearDiscountConditions();
        $this->clearDiscountConditionGroups();
    }

    private function clearDiscountConditions(): void
    {
        DiscountCondition::where('type', DiscountCondition::DISCOUNT_SYNERGY)
            ->chunk(100, function (Collection $conditions) {
                $conditions->each(function (DiscountCondition $condition) {
                    $conditionArray = $condition->condition;
                    $synergy = $conditionArray[DiscountCondition::FIELD_SYNERGY];
                    $newSynergy = array_values(array_filter(
                        $synergy,
                        fn(int $discountId) => Discount::whereId($discountId)->exists()
                    ));

                    if (empty($newSynergy)) {
                        dump("synergy condition id: {$condition->id} was deleted");
                        $condition->delete();
                    } elseif ($synergy != $newSynergy) {
                        dump("synergy condition id: {$condition->id} was changed");
                        $condition->setSynergy($newSynergy);
                        $condition->save();
                    }
                });
            });
    }

    private function clearDiscountConditionGroups(): void
    {
        DiscountConditionGroup::chunk(100, function (Collection $conditionGroups) {
            $conditionGroups->each(function (DiscountConditionGroup $conditionGroup) {
                if (!Discount::whereId($conditionGroup->discount_id)->exists()) {
                    $conditionGroup->delete();
                    dump("Condition group id: {$conditionGroup->id} was deleted");
                }
            });
        });
    }
}
