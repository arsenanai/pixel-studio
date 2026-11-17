<?php

declare(strict_types=1);

namespace App\Data\Reports;

use App\ValueObjects\Money;
use JsonSerializable;

final readonly class ReconciledDay implements JsonSerializable
{
    public function __construct(
        public string $date,
        public int $ourGenerations,
        public int $theirGenerations,
        public Money $ourCost,
        public Money $theirCost,
    ) {}

    public function matches(): bool
    {
        return $this->ourCost->equals($this->theirCost) && $this->ourGenerations === $this->theirGenerations;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'date' => $this->date,
            'ourGenerations' => $this->ourGenerations,
            'theirGenerations' => $this->theirGenerations,
            'ourCost' => $this->ourCost,
            'theirCost' => $this->theirCost,
            'difference' => $this->ourCost->subtract($this->theirCost),
            'matches' => $this->matches(),
        ];
    }
}
