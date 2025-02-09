<?php

namespace App\Models;

use Illuminate\Support\Collection;

trait Hash
{
    abstract public function getFillable();

    public function getHash(): string
    {
        $key = [];
        $props = $this->getFillable();
        foreach ($props as $prop) {
            if (!isset($this->$prop)) {
                continue;
            }

            $key[$prop] = is_array($this->$prop) || is_object($this->$prop)
                ? json_encode(static::deepSort($this->$prop), JSON_NUMERIC_CHECK)
                : $this->$prop;
        }

        return md5(implode('-', $key));
    }

    public static function hashDiffItems(Collection $oldItems, Collection $newItems): array
    {
        return [
            'added' => static::hashDiff($newItems, $oldItems),
            'removed' => static::hashDiff($oldItems, $newItems),
        ];
    }

    public static function hashDiff(Collection $a, Collection $b): Collection|self
    {
        $bHashes = $b->map(fn(self $item) => $item->getHash());

        if ($bHashes->isEmpty()) {
            return $a;
        }

        return $a->filter(fn(self $item) => !$bHashes->contains($item->getHash()));
    }

    protected static function deepSort($data): array
    {
        if (!is_object($data) && !is_array($data)) {
            return $data;
        }

        foreach ($data as $k => $v) {
            if (is_object($v) || is_array($v)) {
                $data[$k] = static::deepSort($v);
            }
        }

        if (is_array($data)) {
            ksort($data);
        }

        return $data;
    }
}
