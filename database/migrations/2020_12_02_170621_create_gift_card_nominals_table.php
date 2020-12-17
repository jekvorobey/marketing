<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGiftCardNominalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gift_card_nominals', function (Blueprint $table) {
            // ID
            $table->bigIncrements('id');

            // Статус: 1 - активный, 0 - неактивный
            $table->tinyInteger('status')->unsigned()->default(0);

            // Номинал в рублях
            $table->integer('price');

            // Срок активации в днях (0 - бесконечно)
            $table->integer('activation_period')->default(0);

            // Период действия (с момента активации) в днях (0 - бесконечно)
            $table->integer('validity')->default(0);

            // Сколько сертификатов с этим номиналом еще возможно приорести (0 - бесконечно)
            $table->integer('amount')->default(0);

            // Автор, кто создал этот номинал
            $table->bigInteger('creator_id');

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
        Schema::dropIfExists('gift_card_nominals');
    }
}
