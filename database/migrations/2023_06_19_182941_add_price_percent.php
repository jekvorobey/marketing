<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('prices', static function (Blueprint $table) {
            $table->float('percent_retail')->after('price_retail')->nullable();
            $table->float('percent_prof')->after('price_retail')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('prices', static function (Blueprint $table) {
            $table->dropColumn('percent_retail');
            $table->dropColumn('percent_prof');
        });
    }
};
