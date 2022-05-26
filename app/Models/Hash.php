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

    /**
     * @param self[] $oldItems
     * @param self[] $newItems
     *
     * @return array
     */
    public static function hashDiffItems(Collection $oldItems, Collection $newItems)
    {
        return [
            'added' => static::hashDiff($newItems, $oldItems),
            'removed' => static::hashDiff($oldItems, $newItems),
        ];
    }

    /**
     * @param Collection|self[] $a
     * @param Collection|self[] $b
     */
    public static function hashDiff(Collection $a, Collection $b)
    {
        $bHashes = $b->map(fn(self $item) => $item->getHash());

        if ($bHashes->isEmpty()) {
            return $a;
        }

        return $a->filter(fn(self $item) => !$bHashes->contains($item->getHash()));
    }

    /**
     * @param $data
     *
     * @return array
     */
    protected static function deepSort($data)
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
