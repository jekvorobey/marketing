<?php

namespace App\Services\Calculator\Bonus;

use App\Models\Option\Option;
use App\Services\Calculator\AbstractCalculator;

/**
 * Class BonusSpentCalculator
 * @package App\Services\Calculator\Bonus
 */
class BonusSpentCalculator extends AbstractCalculator
{
    const DEFAULT_BONUS_PER_RUBLES = 1;
    const DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT = 0;
    const DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_ORDER = 0;

    /**
     * @var array
     */
    protected $options = [];

    public function calculate()
    {
        if ($this->input->bonus <= 0) {
            return;
        }

        $options = Option::query()
            ->whereIn('key', [
                Option::KEY_BONUS_PER_RUBLES,
                Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT,
                Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER,
            ])
            ->get()
            ->pluck('value', 'key');

        $this->options = [
            Option::KEY_BONUS_PER_RUBLES => $options[Option::KEY_BONUS_PER_RUBLES]['value'] ?? self::DEFAULT_BONUS_PER_RUBLES,
            Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT => $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT]['value'] ?? self::DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT,
            Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER => $options[Option::KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER]['value'] ?? self::DEFAULT_MAX_DEBIT_PERCENTAGE_FOR_ORDER,
        ];

        // todo
    }
}
