<?php


namespace App\Models\History;

use App\Services\History\HistoryInterface;
use Greensight\CommonMsa\Models\AbstractModel;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tag
 * @property int $user_id
 * @property array|null $user
 * @property string $event
 * @property string $entity_type
 * @property int $entity_id
 * @property array $data
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static History|null find($id)
 * @method static History|null findOrFail($id)
 */
class History extends AbstractModel
{
    protected $table = 'history';
    protected static $restIncludes = ['entity'];

    protected $casts = [
        'data' => 'array'
    ];

    protected $fillable = [
        'tag',
        'user_id',
        'event',
        'entity_type',
        'entity_id',
        'data',
    ];

    public function entity(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public static function makeHistory(string $event, HistoryInterface $model, $save = true): History
    {
        $user = resolve(RequestInitiator::class);

        $history = new self();
        $history->tag = $model->getHistoryTag();
        $history->event = $event;
        $history->user_id = $user->userId();
        $history->entity_id = $model->getHistoryEntityId();
        $history->entity_type = $model->getHistoryEntityType();
        $history->data = $model->getHistoryData($event);

        if ($save) {
            $history->save();
        }

        return $history;
    }
}
