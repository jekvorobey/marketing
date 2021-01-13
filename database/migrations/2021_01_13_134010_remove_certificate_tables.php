<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RemoveCertificateTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('gift_card_reports');
        Schema::dropIfExists('history');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('gift_card_orders');
        Schema::dropIfExists('gift_card_nominal_designs');
        Schema::dropIfExists('gift_card_designs');
        Schema::dropIfExists('gift_card_nominals');
        DB::table('options')->where('key', 'GIFT_CERTIFICATE_CONTENT')->delete();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}
