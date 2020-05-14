<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Option\Option;

class InsertDebitPercentageToOptionsTable extends Migration
{
    const MAX_DEBIT_PERCENTAGE_FOR_PRODUCT = 100;
    const MAX_DEBIT_PERCENTAGE_FOR_ORDER = 100;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $option = Option::query()->where('key', Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT)->first();
        if (!$option) {
            DB::table('options')->insert([
                'key' => Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT,
                'value' => json_encode(['value' => self::MAX_DEBIT_PERCENTAGE_FOR_PRODUCT]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        $option = Option::query()->where('key', Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER)->first();
        if (!$option) {
            DB::table('options')->insert([
                'key' => Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER,
                'value' => json_encode(['value' => self::MAX_DEBIT_PERCENTAGE_FOR_ORDER]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $option = Option::query()->where('key', Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT)->first();
        if ($option) {
            $option->delete();
        }

        $option = Option::query()->where('key', Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER)->first();
        if ($option) {
            $option->delete();
        }
    }
}
