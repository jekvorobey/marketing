<?php

namespace App\Console\Commands;

use App\Models\Discount\Discount;
use Illuminate\Console\Command;

/**
 * Class CancelExpiredOrders
 * @package App\Console\Commands
 */
class ExpiredDiscountStatus extends Command
{
    /** @var string */
    protected $signature = 'discount:expired';
    /** @var string */
    protected $description = 'Приостановить скидки, у которых истёк период действия';

    /**
     * Вместо массового обновления меняем статус в цикле (нужно для логирования изменений)
     */
    public function handle()
    {
        Discount::query()
            ->where('status', Discount::STATUS_ACTIVE)
            ->expired()
            ->each(function (Discount $discount) {
                $discount->status = Discount::STATUS_EXPIRED;
                $discount->save();
            });
    }
}
