<?php

use App\Models\Discount\Discount;
use App\Models\Discount\LogicalOperator;
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
        Schema::create('discount_condition_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Discount::class);
            $table->integer('logical_operator')->default(LogicalOperator::AND);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('discount_condition_groups');
    }
};
