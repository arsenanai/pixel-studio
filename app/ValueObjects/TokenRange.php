<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

final readonly class TokenRange
{
    public function __construct(public int $min, public int $max)
    {
        if ($min < 0 || $max < $min) {
            throw new InvalidArgumentException("[{$min}, {$max}] is not a valid token range.");
        }
    }

    public function midpoint(): int
    {
        return intdiv($this->min + $this->max, 2);
    }
}
