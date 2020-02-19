<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DiscountFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'user_id')->unsigned()->after('id');
            $table->dropColumn('approval_status');
            $table->dropColumn('sponsor');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->addColumn('tinyInteger', 'sponsor')->unsigned();
            $table->addColumn('tinyInteger', 'approval_status')->unsigned();
            $table->dropColumn('user_id');
        });
    }
}
