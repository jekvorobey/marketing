<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDiscountPublicEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discount_public_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('discount_id')->unsigned();
            $table->bigInteger('ticket_type_id')->unsigned();
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
        Schema::dropIfExists('discount_public_events');
    }
}
