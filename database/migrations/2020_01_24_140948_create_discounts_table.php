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
            $table->tinyInteger('sponsor'); /** Спонсор скидки */
            $table->bigInteger('merchant_id')->nullable(); /** Создатель */
            $table->tinyInteger('type')->unsigned(); /** Тип скидки */
            $table->string('name', 255); /** Название скидки */
            $table->tinyInteger('value_type')->unsigned(); /** Тип значения: проценты или рубли */
            $table->integer('value')->unsigned(); /** Значение */
            $table->tinyInteger('approval_status')->unsigned(); /** Статус заявки мерчанта на скидку */
            $table->tinyInteger('status')->unsigned();  /** Статус скидки */
            $table->timestamp('start_date', 0)->nullable();  /** Срок действия от */
            $table->timestamp('end_date', 0)->nullable();  /** Срок действия до */
            $table->boolean('promo_code_only'); /** Доступен только по промокоду */
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
