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
            $table->tinyInteger('type');
            $table->string('name', 255)->nullable();
            $table->tinyInteger('value_type');
            $table->integer('value')->unsigned();
            $table->integer('region_id')->unsigned()->nullable();
            $table->tinyInteger('approval_status');
            $table->tinyInteger('status');
            $table->integer('validity')->unsigned()->nullable();
            $table->timestamp('started_at', 0)->nullable();
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
