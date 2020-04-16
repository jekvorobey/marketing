<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBonusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bonuses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255); /** Название скидки */
            $table->tinyInteger('status')->unsigned();  /** Статус скидки */
            $table->tinyInteger('type')->unsigned(); /** Тип бонуса */
            $table->integer('value')->unsigned(); /** Размер бонуса */
            $table->tinyInteger('value_type')->unsigned(); /** Тип значения: проценты или рубли */
            $table->integer('valid_period')->unsigned()->nullable(); /** Срок жизни бонусов (в днях) */
            $table->date('start_date')->nullable();  /** Срок действия от */
            $table->date('end_date')->nullable();  /** Срок действия до */
            $table->boolean('promo_code_only'); /** Доступны только по промокоду */
            $table->timestamps();
        });

        Schema::create('bonus_offers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('bonus_id')->unsigned();
            $table->bigInteger('offer_id')->unsigned();
            $table->boolean('except');
            $table->timestamps();

            $table->foreign('bonus_id')
                ->references('id')
                ->on('bonuses')
                ->onDelete('cascade');
        });

        Schema::create('bonus_brands', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('bonus_id')->unsigned();
            $table->bigInteger('brand_id')->unsigned();
            $table->boolean('except');
            $table->timestamps();

            $table->foreign('bonus_id')
                ->references('id')
                ->on('bonuses')
                ->onDelete('cascade');;
        });

        Schema::create('bonus_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('bonus_id')->unsigned();
            $table->bigInteger('category_id')->unsigned();
            $table->boolean('except');
            $table->timestamps();

            $table->foreign('bonus_id')
                ->references('id')
                ->on('bonuses')
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
        Schema::dropIfExists('bonus_categories');
        Schema::dropIfExists('bonus_brands');
        Schema::dropIfExists('bonus_offers');
        Schema::dropIfExists('bonuses');
    }
}
