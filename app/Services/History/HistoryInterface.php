<?php

namespace App\Services\History;

interface HistoryInterface
{
    public function getHistoryTag(): ?string;

    public function getHistoryEntityId(): int;

    public function getHistoryEntityType(): string;

    public function getHistoryData(string $event): array;

}
