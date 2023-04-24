<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prices', static function (Blueprint $table) {
            $table->bigInteger('merchant_id')->nullable()->after('offer_id');
            $table->float('price_retail')->nullable()->after('price');
            $table->float('price_base')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('prices', static function (Blueprint $table) {
            $table->dropColumn('price_retail');
            $table->dropColumn('price_base');
        });
    }
};
