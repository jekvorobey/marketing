<?php


namespace App\Models\Certificate;

use App\Services\History\HasHistory;
use App\Services\History\HistoryInterface;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Support\Carbon;

/**
 * @property int    $id
 * @property string $name
 * @property string $preview
 * @property int    $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Design|null find($id)
 * @method static Design|null findOrFail($id)
 */
class Design extends AbstractModel implements HistoryInterface
{
    use HasHistory;

    protected $table = 'gift_card_designs';
    public static $historyEvents = ['created', 'updated', 'deleted'];
    public $historyTag = 'gift_card';

    protected $fillable = [
        'name',
        'preview',
        'status'
    ];
}
