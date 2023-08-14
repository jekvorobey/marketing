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
        Schema::create('prices_by_roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('price_id')->unsigned();
            $table->foreign('price_id')->references('id')->on('prices')->onDelete('CASCADE');
            $table->integer('role')->unsigned();
            $table->float('price');
            $table->float('percent_by_base_price')->nullable();
            $table->timestamps();

            $table->unique(['price_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prices_by_roles');
    }
};
