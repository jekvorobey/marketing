<?php

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountConditionGroup;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Discount::has('conditions')
            ->cursor()
            ->each(function ($discount) {
                $this->wrapConditions($discount);
            });
    }

    /**
     * @param Discount $discount
     * @return void
     */
    protected function wrapConditions(Discount $discount): void
    {
        if ($discount->conditionGroups->isNotEmpty()) {
           return;
        }

        /** @var DiscountConditionGroup $conditionGroup */
        $conditionGroup = $discount->conditionGroups()->create();

        $discount->conditions()->update([
            'discount_condition_group_id' => $conditionGroup->id
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        DiscountConditionGroup::query()->truncate();
    }
};
