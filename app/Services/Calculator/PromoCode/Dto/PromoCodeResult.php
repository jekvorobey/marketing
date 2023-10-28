<?php

namespace App\Services\Calculator\PromoCode\Dto;

class PromoCodeResult
{
    /**
     * Объект результата работы промокода
     */

    protected bool $isApplied;
    protected ?float $change = null;

    /**
     * @param bool $isApplied
     * @param float|null $change
     */
    public function __construct(bool $isApplied, ?float $change = null)
    {
        $this->isApplied = $isApplied;
        $this->change = $change;
    }

    /**
     * @param float|null $change
     * @return PromoCodeResult
     */
    public static function applied(?float $change): PromoCodeResult
    {
        return new self(true, $change);
    }

    /**
     * @return PromoCodeResult
     */
    public static function notApplied(): PromoCodeResult
    {
        return new self(false);
    }

    /**
     * @return bool
     */
    public function isApplied(): bool
    {
        return $this->isApplied;
    }

    /**
     * @return float|null
     */
    public function getChange(): ?float
    {
        return $this->change;
    }

}
