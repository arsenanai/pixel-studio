<?php

declare(strict_types=1);

namespace App\Enums;

enum GenerationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    // Both outcomes share the top rank, so whichever terminal state lands first wins.
    public function rank(): int
    {
        return match ($this) {
            self::Queued => 10,
            self::Processing => 20,
            self::Succeeded, self::Failed => 30,
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
