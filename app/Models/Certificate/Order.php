<?php

namespace App\Models\Certificate;

use App\Services\History\HasHistory;
use App\Services\History\HistoryInterface;
use Greensight\CommonMsa\Models\AbstractModel;
use Greensight\Oms\Dto\Payment\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 *
 * @property int         $order_id
 * @property int         $order_number
 * @property int         $payment_status
 *
 * @property int         $customer_id
 * @property string      $comment
 *
 * @property int         $nominal_id
 * @property int         $design_id
 * @property int         $qty
 * @property int         $price
 *
 * @property Carbon      $delivery_at
 * @property Carbon|null $notified_at
 * @property Carbon|null $paid_at
 *
 * @property int         $is_anonymous
 * @property int         $is_to_self
 *
 * @property string      $from_name
 * @property string      $from_phone
 * @property string      $from_email
 *
 * @property string      $to_name
 * @property string      $to_phone
 * @property string      $to_email
 *
 * @property Card[]      $cards
 * @property Design      $design
 * @property Nominal     $nominal
 *
 * @method static Order|null find($id)
 * @method static Order|null findOrFail($id)
 * @method static Builder    paid()
 */
class Order extends AbstractModel implements HistoryInterface
{
    use HasHistory;

    protected $table = 'gift_card_orders';

    public static $historyEvents = ['created', 'updated', 'deleted'];
    public $historyTag = 'gift_card';
    protected static $restIncludes = ['design', 'nominal', 'cards'];

    protected $fillable = [
        'order_id',
        'order_number',
        'payment_status',

        'customer_id',
        'comment',

        'nominal_id',
        'design_id',
        'qty',
        'price',

        'delivery_at',
        'notified_at',
        'paid_at',

        'is_anonymous',
        'is_to_self',

        'to_name',
        'to_email',
        'to_phone',

        'from_name',
        'from_email',
        'from_phone',
    ];

    protected $casts = [
        'delivery_at' => 'datetime',
        'notified_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function cards()
    {
        return $this->hasMany(Card::class, 'gift_card_order_id');
    }

    public function nominal()
    {
        return $this->belongsTo(Nominal::class);
    }

    public function design()
    {
        return $this->belongsTo(Design::class);
    }

    public function scopePaid(Builder $builder)
    {
        return $builder->whereIn('payment_status', [PaymentStatus::PAID, PaymentStatus::HOLD]);
    }

    public function setPaymentStatus($status): self
    {
        $isPaid = in_array($status, [PaymentStatus::PAID, PaymentStatus::HOLD]);

        $this->payment_status = $status;

        if ($isPaid)
            $this->paid_at = Carbon::now();

        $this->save();

        if ($isPaid) {
            foreach ($this->cards as $card)
                $card->onPayment();
        }

        return $this;
    }

    /**
     * @param int $orderId
     * @return AbstractModel|Order|null
     */
    public static function findByOrderId(int $orderId): ?Order
    {
        return self::query()->where('order_id', $orderId)->first();
    }

    /**
     * @param int $orderId
     * @return AbstractModel|Order|null
     * @throws ModelNotFoundException
     */
    public static function findByOrderIdOrFail(int $orderId): Order
    {
        return self::query()->where('order_id', $orderId)->firstOrFail();
    }
}
