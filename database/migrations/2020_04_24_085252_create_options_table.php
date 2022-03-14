<?php

use Carbon\Carbon;
use Greensight\CommonMsa\Dto\RoleDto;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('options', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('key')->unique();
            $table->json('value')->nullable();

            $table->timestamps();
        });

        DB::table('options')->insert([
            'key' => 'KEY_BONUS_PER_RUBLES',
            'value' => json_encode(['value' => 1]),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        DB::table('options')->insert([
            'key' => 'KEY_ROLES_AVAILABLE_FOR_BONUSES',
            'value' => json_encode(['value' => [RoleDto::ROLE_SHOWCASE_PROFESSIONAL]]),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('options');
    }
}
