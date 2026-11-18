<?php

declare(strict_types=1);

namespace App\Enums;

enum HistoryFilter: string
{
    case All = 'all';
    case Active = 'active';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /** @return list<GenerationStatus> */
    public function statuses(): array
    {
        return match ($this) {
            self::All => GenerationStatus::cases(),
            self::Active => [GenerationStatus::Queued, GenerationStatus::Processing],
            self::Succeeded => [GenerationStatus::Succeeded],
            self::Failed => [GenerationStatus::Failed],
        };
    }
}
