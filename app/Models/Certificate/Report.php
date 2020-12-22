<?php


namespace App\Models\Certificate;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Support\Carbon;

/**
 * @property int    $id
 * @property array  $data
 * @property int    $creator_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Report|null find($id)
 * @method static Report|null findOrFail($id)
 */
class Report extends AbstractModel
{
    protected $table = 'gift_card_reports';

    protected $fillable = [
        'data',
        'creator_id'
    ];

    protected $casts = [
        'data' => 'array'
    ];
}
