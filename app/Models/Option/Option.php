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
    protected $table = 'options';

    protected $casts = [
        'value' => 'array'
    ];
}
