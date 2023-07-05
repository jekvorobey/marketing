<?php

namespace app\Console\Commands\OneTime;

use App\Models\PromoCode\PromoCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MovePromocodeDiscountRelation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onetime:move-promocode-discount-relation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Перенести данные о скидках, связанных с промокодами в отдельную таблицу. OneMany -> ManyMany';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $now = now();
        $insertData = PromoCode::whereType(PromoCode::TYPE_DISCOUNT)
            ->select(['id', 'discount_id'])
            ->get()
            ->map(fn($p) => [
               'discount_id' => $p->discount_id,
               'promo_code_id' => $p->id,
               'created_at' => $now,
               'updated_at' => $now,
            ])
            ->toArray();

        DB::table('discount_promo_code')->insert($insertData);

        return 0;
    }
}
