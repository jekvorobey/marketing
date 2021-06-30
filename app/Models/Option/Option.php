<?php

namespace App\Models\Option;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Option
 * @package App\Models
 *
 * @property int $id
 * @property string $key
 * @property array $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Option extends Model
{
    public const KEY_BONUS_PER_RUBLES = 'KEY_BONUS_PER_RUBLES';
    public const KEY_ROLES_AVAILABLE_FOR_BONUSES = 'KEY_ROLES_AVAILABLE_FOR_BONUSES';
    public const KEY_ORDER_ACTIVATION_BONUS_DELAY = 'KEY_ORDER_ACTIVATION_BONUS_DELAY';
    public const KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT = 'KEY_MAX_DEBIT_PERCENTAGE_FOR_PRODUCT';
    public const KEY_MAX_DEBIT_PERCENTAGE_FOR_DISCOUNT_PRODUCT = 'KEY_MAX_DEBIT_PERCENTAGE_FOR_DISCOUNT_PRODUCT';
    public const KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER = 'KEY_MAX_DEBIT_PERCENTAGE_FOR_ORDER';

    public const DEFAULT_BONUS_PER_RUBLES = 1;

    protected $table = 'options';

    protected $casts = [
        'value' => 'array',
    ];
}
