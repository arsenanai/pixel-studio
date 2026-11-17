<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\ValueObjects\Money;
use RuntimeException;

final class InsufficientCredits extends RuntimeException
{
    public function __construct(public readonly Money $required, public readonly Money $available)
    {
        parent::__construct(
            "This image could cost up to {$required->format()}, and you have {$available->format()} available. Buy credits to continue.",
        );
    }
}
