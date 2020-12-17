<?php

namespace App\Services\History;

use App\Models\History\History;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

trait HasHistory
{
    protected $originalValues = [];

    protected static function bootHasHistory()
    {
        static::updating(function (Model $model) {
            $model->originalValues = $model->getOriginal();
        });
        static::deleting(function (Model  $model) {
            $model->originalValues = $model->getOriginal();
        });

        static::eventsToBeRecorded()->each(function ($eventName) {
            return static::$eventName(function (HistoryInterface $model) use ($eventName) {

                $history = History::makeHistory($eventName, $model, false);

                if (method_exists($model, 'tapHistory')) {
                    call_user_func([$model, 'tapHistory'], $history, $eventName);
                }

                $history->save();
            });
        });
    }

    protected static function eventsToBeRecorded(): Collection
    {
        return isset(static::$historyEvents) ? collect(static::$historyEvents) : collect();
    }

    public function getHistoryTag(): ?string
    {
        return property_exists($this, 'historyTag') ? $this->historyTag : null;
    }

    protected function getHistoryIgnoreAttributes(): array
    {
        return property_exists($this, 'historyIgnore') ? $this->historyIgnore : ['updated_at'];
    }

    public function getHistoryEntityId(): int
    {
        return $this->getKey();
    }

    public function getHistoryEntityType(): string
    {
        return $this->getMorphClass();
    }

    public function getHistoryData(string $event): array
    {
        $ignored = $this->getHistoryIgnoreAttributes();

        if ($event === 'deleted') {
            return ['old' => Arr::except($this->originalValues, $ignored)];
        }

        if ($event === 'created') {
            return ['new' => Arr::except($this->getAttributes(), $ignored)];
        }

        $changes = ['old' => [], 'new' => []];

        foreach ($this->getAttributes() as $key => $value) {
            if (in_array($key, $ignored))
                continue;

            if (! $this->isValueUnchanged($key, $value)) {
                $changes['old'][$key] = $this->originalValues[$key] ?? null;
                $changes['new'][$key] = $value;
            }
        }

        return (!empty($changes['old'])) ? $changes : [];
    }

    public function isValueUnchanged($key, $current): bool
    {
        if (! array_key_exists($key, $this->originalValues)) {
            return false;
        }

        $original = $this->originalValues[$key];

        if ($current === $original) {
            return true;
        } elseif (is_null($current)) {
            return false;
        } elseif ($this->isDateAttribute($key)) {
            return $this->fromDateTime($current) ===
                $this->fromDateTime($original);
        } elseif ($this->hasCast($key)) {
            return $this->castAttribute($key, $current) ===
                $this->castAttribute($key, $original);
        }

        return is_numeric($current) && is_numeric($original)
            && strcmp((string) $current, (string) $original) === 0;
    }
}
