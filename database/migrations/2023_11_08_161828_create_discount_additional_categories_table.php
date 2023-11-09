<?php

use App\Models\Discount\DiscountCategory;
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
        Schema::create('discount_additional_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(DiscountCategory::class);
            $table->bigInteger('category_id')->unsigned();
            $table->boolean('except')->default(false);
            $table->timestamps();

            $table->foreign('discount_category_id')
                ->references('id')
                ->on('discount_categories')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('discount_additional_categories');
    }
};
