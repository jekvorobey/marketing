<?php

namespace App\Console\Commands;

use App\Models\PromoCode\PromoCode;
use Illuminate\Console\Command;

/**
 * Class ExpiredPromoCodeStatus
 * @package App\Console\Commands
 */
class ExpiredPromoCodeStatus extends Command
{
    /** @var string */
    protected $signature = 'promo-code:expired';
    /** @var string */
    protected $description = 'Приостановить промокоды, у которых истёк период действия';

    /**
     * Вместо массового обновления меняем статус в цикле (нужно для логирования изменений)
     */
    public function handle()
    {
        PromoCode::query()
            ->where('status', PromoCode::STATUS_ACTIVE)
            ->expired()
            ->each(function (PromoCode $promoCode) {
                $promoCode->status = PromoCode::STATUS_EXPIRED;
                $promoCode->save();
            });
    }
}
