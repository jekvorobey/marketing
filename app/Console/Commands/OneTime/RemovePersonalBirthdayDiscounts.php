<?php

namespace App\Console\Commands\OneTime;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RemovePersonalBirthdayDiscounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onetime:remove-personal-birthday-discounts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Удалить персональные скидки к ДР, а также убрать суммирования с ними из других скидок';

    public function handle()
    {
        /** @var Collection $discounts */
        $discountsToDelete = Discount::where('name', 'like', '%HAPPY2U Customer ID:%')->get();
        $discountsToDeleteIds = $discountsToDelete->pluck('id')->toArray();

        /** @var Discount $discount */
        foreach ($discountsToDelete as $discountToDelete) {
            $discountToDelete->offers()->delete();
            $discountToDelete->bundleItems()->delete();
            $discountToDelete->brands()->delete();
            $discountToDelete->categories()->delete();
            $discountToDelete->roles()->delete();
            $discountToDelete->segments()->delete();
            $discountToDelete->conditions()->delete();
            $discountToDelete->conditionGroups()->delete();
            $discountToDelete->publicEvents()->delete();
            $discountToDelete->bundles()->delete();
            $discountToDelete->childDiscounts()->delete();
            $discountToDelete->merchants()->delete();
            $discountToDelete->productProperties()->delete();
            $discountToDelete->delete();
            dump("Discount {$discountToDelete->id}:{$discountToDelete->name} deleted");
        }

        $this->removeSummationsWithDeletedDiscounts($discountsToDeleteIds);
    }

    private function removeSummationsWithDeletedDiscounts(array $deletedIds): void
    {
        $discountsWithSynergy = Discount::whereHas('conditions', function (Builder $query) {
            $query->where('type', DiscountCondition::DISCOUNT_SYNERGY);
        })->with('conditions')->get();

        $discountsWithSynergy->each(function (Discount $discount) use ($deletedIds) {
            /** @var DiscountCondition $synergyCondition */
           if ($synergyCondition = $discount->getSynergyCondition()) {
                $synergyIds = $synergyCondition->condition['synergy'] ?? [];
                if (!empty($synergyIds)) {
                    $synergyIds = array_values(array_diff($synergyIds, $deletedIds));
                    $synergyCondition->setSynergy($synergyIds);
                    $synergyCondition->save();
                    dump("Discount {$discount->id}:{$discount->name} synergy changed");
                }
           }
        });
    }
}
