<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGiftCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('gift_card_order_id');

            $table->unsignedBigInteger('nominal_id');
            $table->unsignedBigInteger('design_id');

            $table->smallInteger('status');

            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();

            $table->string('pin')->nullable();

            $table->integer('price')->default(0);
            $table->integer('balance')->default(0);

            $table->dateTime('activate_before')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('paid_at')->nullable();

            $table->foreign('nominal_id')
                ->references('id')
                ->on('gift_card_nominals');

            $table->foreign('design_id')
                ->references('id')
                ->on('gift_card_designs');

            $table->foreign('gift_card_order_id')
                ->references('id')
                ->on('gift_card_orders')
                ->onDelete('cascade');

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
        Schema::dropIfExists('gift_cards');
    }
}
