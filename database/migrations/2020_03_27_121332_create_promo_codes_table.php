<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePromoCodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('creator_id'); /** Создатель */
            $table->bigInteger('owner_id')->nullable(); /** РП, который промоутирует промокод */
            $table->string('name', 255); /** Название промокода */
            $table->string('code', 255)->unique(); /** Код */
            /** Ограничение на количество применений, null – ограничений нет */
            $table->integer('counter')->nullable();
            $table->date('start_date')->nullable(); /** Срок действия от */
            $table->date('end_date')->nullable(); /** Срок действия до */
            $table->tinyInteger('status')->unsigned(); /** Статус промокода */
            $table->tinyInteger('type')->unsigned(); /** Тип промокода */
            $table->bigInteger('discount_id')->unsigned()->nullable(); /** ID скидки */
            $table->bigInteger('gift_id')->unsigned()->nullable(); /** ID подарка */
            $table->bigInteger('bonus_id')->unsigned()->nullable(); /** ID бонуса */

            /**
             * Условия по которым может применяться промокод
             * Например, привязка к определенному аккаунту, сегменту, группе, функциональной роли и ее уровню
             * (возможно добавление других условий)
             */
            $table->json('conditions')->nullable();
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
        Schema::dropIfExists('promo_codes');
    }
}
