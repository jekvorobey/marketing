<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tag')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event');
            $table->morphs('entity');
            $table->jsonb('data')->nullable();
            $table->timestamps();

            $table->index(['tag']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('history');
    }
}
