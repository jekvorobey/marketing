<?php

namespace App\Services\Calculator\Discount\Checker\Traits;

trait WithExtraParams
{
    protected array $extraParams = [];

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function addExtraParam(string $key, mixed $value): self
    {
        $this->extraParams[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function getExtraParam(string $key, mixed $default = null): mixed
    {
        return $this->extraParams[$key] ?? $default;
    }

    /**
     * @param array $extraParams
     * @return $this
     */
    public function setExtraParams(array $extraParams): self
    {
        $this->extraParams = $extraParams;
        return $this;
    }
}
