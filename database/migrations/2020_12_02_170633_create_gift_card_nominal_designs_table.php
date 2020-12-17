<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGiftCardNominalDesignsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gift_card_nominal_designs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('nominal_id');
            $table->unsignedBigInteger('design_id');

            $table->foreign('nominal_id')
                ->references('id')
                ->on('gift_card_nominals')
                ->onDelete('cascade');

            $table->foreign('design_id')
                ->references('id')
                ->on('gift_card_designs')
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
        Schema::dropIfExists('gift_card_nominal_designs');
    }
}
