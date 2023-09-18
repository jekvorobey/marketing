<?php

use App\Models\Discount\DiscountConditionGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('discount_conditions', function (Blueprint $table) {
            $table->foreignIdFor(DiscountConditionGroup::class)->after('discount_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('discount_conditions', function (Blueprint $table) {
            $table->dropColumn('discount_condition_group_id');
        });
    }
};
