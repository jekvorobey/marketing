<?php

namespace App\Console\Commands;

use App\Models\Bonus\Bonus;
use Illuminate\Console\Command;

/**
 * Class ExpiredBonusStatus
 * @package App\Console\Commands
 */
class ExpiredBonusStatus extends Command
{
    /** @var string */
    protected $signature = 'bonus:expired';
    /** @var string */
    protected $description = 'Приостановить бонусы, у которых истёк период действия';

    /**
     * Вместо массового обновления меняем статус в цикле (нужно для логирования изменений)
     */
    public function handle()
    {
        $bonuses = Bonus::query()->where('status', Bonus::STATUS_ACTIVE)->get();
        /** @var Bonus $bonus */
        foreach ($bonuses as $bonus) {
            if ($bonus->isExpired()) {
                $bonus->status = Bonus::STATUS_EXPIRED;
                $bonus->save();
            }
        }
    }
}
