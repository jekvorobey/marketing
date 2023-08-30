<?php

use App\Models\Discount\Discount;
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
        Schema::table('discounts', function (Blueprint $table) {
            $table->boolean('show_on_showcase')->default(true);
            $table->integer('showcase_display_type')->default(Discount::DISPLAY_TYPE_PERCENT);
            $table->boolean('show_original_price')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn('show_on_showcase');
            $table->dropColumn('showcase_display_type');
            $table->dropColumn('show_original_price');
        });
    }
};
