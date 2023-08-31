<?php

use App\Models\PromoCode\PromoCode;
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
    public function up()
    {
        $this->prepareTable();

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->string('code')->charset('utf8mb4')->collation('utf8mb4_0900_ai_ci')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }

    /**
     * Добавить к одинаковым кодам постфиксы
     * @return void
     */
    private function prepareTable(): void
    {
        $same = [];
        $grouped = [];

        PromoCode::cursor()->each(function (PromoCode $p) use (&$grouped, &$same) {
            $code = mb_strtolower($p->code);

            if (isset($grouped[$code])) {
                $grouped[$code][] = $p;
            } else {
                $grouped[$code] = [$p];
            }

            if (count($grouped[$code]) > 1) {
                $same[$code] = $grouped[$code];
            }
        });

        foreach ($same as $promocodes) {
            array_shift($promocodes);
            foreach ($promocodes as $i => $promocode) {
                $promocode->code .=  '_' . $i;
                $promocode->save();
            }
        }
    }
};
