<?php

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
        Schema::create('discount_product_properties', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('discount_id')->unsigned();
            $table->bigInteger('property_id')->unsigned();
            $table->json('values');
            $table->boolean('except');
            $table->timestamps();

            $table->foreign('discount_id')
                ->references('id')
                ->on('discounts')
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
        Schema::dropIfExists('discount_product_properties');
    }
};
