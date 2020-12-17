<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGiftCardOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gift_card_orders', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_number')->nullable();

            $table->integer('payment_status')->nullable();

            $table->unsignedBigInteger('customer_id')->nullable();

            $table->text('comment')->nullable();

            $table->unsignedBigInteger('nominal_id');
            $table->unsignedBigInteger('design_id');
            $table->integer('qty')->default(1);
            $table->integer('price');

            $table->datetime('delivery_time')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_to_self')->default(false);

            $table->string('to_name')->nullable();
            $table->string('to_email')->nullable();
            $table->string('to_phone')->nullable();

            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_phone')->nullable();

            $table->timestamps();

            $table->foreign('nominal_id')
                ->references('id')
                ->on('gift_card_nominals')
                ->onDelete('restrict');

            $table->foreign('design_id')
                ->references('id')
                ->on('gift_card_designs')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gift_card_orders');
    }
}
