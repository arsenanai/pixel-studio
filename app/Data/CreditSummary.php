<?php

declare(strict_types=1);

namespace App\Data;

use App\ValueObjects\Money;

final readonly class CreditSummary
{
    public function __construct(
        public Money $balance,
        public Money $held,
    ) {}

    public function available(): Money
    {
        return $this->balance->subtract($this->held);
    }
}
