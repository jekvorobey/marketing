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
        $promoCodes = PromoCode::query()->where('status', PromoCode::STATUS_ACTIVE)->get();
        /** @var PromoCode $promoCode */
        foreach ($promoCodes as $promoCode) {
            if ($promoCode->isExpired()) {
                $promoCode->status = PromoCode::STATUS_EXPIRED;
                $promoCode->save();
            }
        }
    }
}
