<?php

namespace App\Console\Commands;

use App\Models\Bonus\Bonus;
use App\Models\GiftCard\GiftCard;
use App\Models\GiftCard\GiftCardDesign;
use App\Models\GiftCard\GiftCardHistory;
use App\Models\GiftCard\GiftCardNominal;
use App\Models\History;
use App\Providers\AuthServiceProvider;
use App\Services\GiftCard\GiftCardTransactionStatus;
use Illuminate\Console\Command;

/**
 * Class ExpiredBonusStatus
 * @package App\Console\Commands
 */
class Sandbox extends Command
{
    /** @var string */
    protected $signature = 's';

    /** @var string */
    protected $description = 'Sandbox';


    public function handle()
    {
        $nominal = GiftCardNominal::find(1);
        $nominal->price = rand(1000, 4000);
        $nominal->save();
//        History::makeHistory('updated', $nominal)->save();
    }
}
