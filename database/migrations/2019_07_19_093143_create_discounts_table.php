<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDiscountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('merchant_id');
            $table->tinyInteger('type')->unsigned();
            $table->string('name', 255)->nullable();
            $table->tinyInteger('value_type')->unsigned();
            $table->integer('value')->unsigned();
            $table->string('promo_code', 255)->nullable();
            $table->integer('region_id')->unsigned()->nullable();
            $table->tinyInteger('approval_status')->unsigned();
            $table->tinyInteger('status')->unsigned();
            $table->integer('validity')->unsigned()->nullable();
            $table->timestamp('start_date', 0)->nullable();
            $table->timestamp('end_date', 0)->nullable();
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
        Schema::dropIfExists('discounts');
    }
}
