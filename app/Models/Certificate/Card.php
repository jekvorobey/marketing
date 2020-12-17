<?php


namespace App\Models\Certificate;

use App\Services\History\HasHistory;
use App\Services\History\HistoryInterface;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 *
 * @property int $gift_card_order_id
 *
 * @property int $nominal_id
 * @property int $design_id
 *
 * @property int $status
 *
 * @property int $customer_id
 * @property int|null $recipient_id
 *
 * @property string $pin
 *
 * @property int $price
 * @property int $balance
 *
 * @property Nominal $nominal
 * @property Design $design
 * @property Order $order
 *
 * @property Carbon|null $activate_before
 * @property Carbon|null $valid_until
 * @property Carbon|null $activated_at
 * @property Carbon|null $notified_at
 * @property Carbon|null $paid_at
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Card|null find($id)
 * @method static Card|null findOrFail($id)
 */
class Card extends AbstractModel implements HistoryInterface
{
    use HasHistory;

    protected $table = 'gift_cards';

    public static $historyEvents = ['created', 'updated', 'deleted'];
    public $historyTag = 'gift_card';
    protected static $restIncludes = ['design', 'nominal', 'order', 'order.cards'];

    const STATUS_NEW = 0;                       // Новая неоплаченная карта
    const STATUS_PAID = 300;                    // Приобретен
    const STATUS_SEND = 301;                    // Отправлен
    const STATUS_ACTIVATED = 302;               // Активирован
    const STATUS_IN_USE = 303;                  // Используется
    const STATUS_COMPLETE = 304;                // Использован
    const STATUS_DEACTIVATED = 305;             // Деактивирован
    const STATUS_EXPIRED_NOT_ACTIVATED = 306;   // Истек срок действия ПС
    const STATUS_EXPIRED = 307;                 // Истек срок действия денежных средств

    protected $fillable = [
        'gift_card_order_id',

        'nominal_id',
        'design_id',

        'status',

        'customer_id',
        'recipient_id',

        'pin',

        'price',
        'balance',

        'activate_before',
        'valid_until',
        'activated_at',
        'notified_at',
        'paid_at'
    ];

    protected $casts = [
        'activate_before' => 'datetime',
        'valid_until' => 'datetime',
        'activated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function buildPin()
    {
        try {
            $bytes = random_bytes(4);
            $this->pin = strtoupper(bin2hex($bytes));
        } catch (\Exception $e) {
            $data = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F'];
            shuffle($data);
            $this->pin = join(array_slice($data, 0, 8));
        }
    }

    public function nominal()
    {
        return $this->belongsTo(Nominal::class);
    }

    public function design()
    {
        return $this->belongsTo(Design::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'gift_card_order_id');
    }

    /**
     * Запускается в момент, когда приходит информация из OMS что заказ с этой картой оплачен
     */
    public function onPayment()
    {
        if ($this->status === self::STATUS_NEW)
        {
            $this->status = self::STATUS_PAID;
            $this->paid_at = Carbon::now();
            $this->buildPin();
            $this->save();
            $this->nominal->onPayment();
        }
    }
}
