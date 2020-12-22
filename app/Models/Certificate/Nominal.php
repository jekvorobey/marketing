<?php


namespace App\Models\Certificate;

use App\Services\History\HasHistory;
use App\Services\History\HistoryInterface;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Support\Carbon;

/**
 * @property int        $price
 * @property int        $status
 * @property int        $activation_period
 * @property int        $validity
 * @property int        $amount
 * @property int        $creator_id
 * @property Carbon     $created_at
 * @property Carbon     $updated_at
 * @property Design[]   $designs
 *
 * @method static Nominal|null find($id)
 * @method static Nominal|null findOrFail($id)
 */
class Nominal extends AbstractModel implements HistoryInterface
{
    use HasHistory;

    protected $table = 'gift_card_nominals';
    public static $historyEvents = ['created', 'updated', 'deleted'];
    public $historyTag = 'gift_card';

    protected static $restIncludes = ['designs'];

    protected $fillable = [
        'price',
        'status',
        'activation_period',
        'validity',
        'amount',
        'creator_id'
    ];


    public function designs()
    {
        return $this->belongsToMany(
            Design::class,
            'gift_card_nominal_designs',
            'nominal_id',
            'design_id'
        );
    }

    public function onPayment()
    {
        if ($this->status && $this->amount > 0) {
            $this->amount--;
            if ($this->amount <= 0)
                $this->status = false;
            $this->save();
        }
    }
}
