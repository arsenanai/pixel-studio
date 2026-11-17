<?php

declare(strict_types=1);

namespace App\Data\Reports;

use App\ValueObjects\Money;
use JsonSerializable;

/** What users were charged and what Pixelforge charged us, for one slice of the report. */
final readonly class SpendTotals implements JsonSerializable
{
    public function __construct(
        public int $succeeded,
        public int $failed,
        public Money $charged,
        public Money $providerCost,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0, Money::zero(), Money::zero());
    }

    public function add(self $other): self
    {
        return new self(
            $this->succeeded + $other->succeeded,
            $this->failed + $other->failed,
            $this->charged->add($other->charged),
            $this->providerCost->add($other->providerCost),
        );
    }

    public function profit(): Money
    {
        return $this->charged->subtract($this->providerCost);
    }

    /** Profit as a share of revenue, e.g. "20.00%"; null when nothing was charged. */
    public function margin(): ?string
    {
        if ($this->charged->isZero()) {
            return null;
        }

        $basisPoints = intdiv($this->profit()->nano * 10_000, $this->charged->nano);

        return sprintf('%d.%02d%%', intdiv($basisPoints, 100), abs($basisPoints % 100));
    }

    /** @return array{succeeded: int, failed: int, charged: Money, providerCost: Money, profit: Money, margin: string|null} */
    public function jsonSerialize(): array
    {
        return [
            'succeeded' => $this->succeeded,
            'failed' => $this->failed,
            'charged' => $this->charged,
            'providerCost' => $this->providerCost,
            'profit' => $this->profit(),
            'margin' => $this->margin(),
        ];
    }
}
