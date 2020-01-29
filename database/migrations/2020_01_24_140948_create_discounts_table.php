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

        Schema::create('discount_offers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('discount_id')->unsigned();
            $table->bigInteger('offer_id')->unsigned();
            $table->boolean('except');
            $table->timestamps();
        });

        Schema::create('discount_brands', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('discount_id')->unsigned();
            $table->bigInteger('brand_id')->unsigned();
            $table->boolean('except');
            $table->timestamps();
        });

        Schema::create('discount_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('discount_id')->unsigned();
            $table->bigInteger('category_id')->unsigned();
            $table->boolean('except');
            $table->timestamps();
        });

        Schema::create('discount_user_roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('discount_id')->unsigned();
            $table->bigInteger('role_id')->unsigned();
            $table->boolean('except');
            $table->timestamps();
        });

        /** Условия возникновения скидки */
        Schema::create('discount_conditions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('discount_id')->unsigned();  /** Скидка */
            $table->bigInteger('type')->unsigned();         /** Тип условия */
            $table->json('condition');                      /** Услвоие */
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
        Schema::dropIfExists('discount_conditions');
        Schema::dropIfExists('discount_user_roles');
        Schema::dropIfExists('discount_segments');
        Schema::dropIfExists('discount_categories');
        Schema::dropIfExists('discount_brands');
        Schema::dropIfExists('discount_products');
        Schema::dropIfExists('discounts');
    }
}
