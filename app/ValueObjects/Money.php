<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * An exact amount of US dollars, held as an integer number of nanodollars (1e-9 USD).
 *
 * Pixelforge publishes rates per 1M tokens and costs to nine decimal places, so
 * every per-token price and every provider cost is a whole number of nanodollars.
 */
final readonly class Money implements JsonSerializable
{
    public const NANO_PER_DOLLAR = 1_000_000_000;

    private function __construct(public int $nano) {}

    public static function fromNano(int $nano): self
    {
        return new self($nano);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    // Parsed as digits: a float cannot hold "0.169080000" exactly.
    public static function fromUsdString(string $usd): self
    {
        if (preg_match('/^(\d{1,9})(?:\.(\d{1,9}))?$/', $usd, $matches) !== 1) {
            throw new InvalidArgumentException("[{$usd}] is not a non-negative USD amount with at most 9 decimal places.");
        }

        $fraction = str_pad($matches[2] ?? '', 9, '0');

        return new self((int) $matches[1] * self::NANO_PER_DOLLAR + (int) $fraction);
    }

    public function add(self $other): self
    {
        return new self($this->nano + $other->nano);
    }

    public function subtract(self $other): self
    {
        return new self($this->nano - $other->nano);
    }

    // ceil, so a markup on an odd nanodollar amount never undercharges.
    public function withMarkup(int $basisPoints): self
    {
        if ($this->nano < 0 || $basisPoints < 0) {
            throw new InvalidArgumentException('Markup applies to non-negative amounts and rates only.');
        }

        return new self(intdiv($this->nano * (10_000 + $basisPoints) + 9_999, 10_000));
    }

    public function equals(self $other): bool
    {
        return $this->nano === $other->nano;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->nano > $other->nano;
    }

    public function isZero(): bool
    {
        return $this->nano === 0;
    }

    /** Exact decimal dollars with at least two places, e.g. "0.3818875", "5.00", "-0.01". */
    public function toDecimalString(): string
    {
        [$sign, $dollars, $fraction] = $this->parts();

        return "{$sign}{$dollars}.{$fraction}";
    }

    /** Exact, for people: "$0.3818875", "$1,250.00", "-$0.01". */
    public function format(): string
    {
        [$sign, $dollars, $fraction] = $this->parts();

        return $sign.'$'.number_format($dollars).'.'.$fraction;
    }

    /** @return array{nano: string, display: string} */
    public function jsonSerialize(): array
    {
        // A string, because browser numbers lose precision above 2^53.
        return ['nano' => (string) $this->nano, 'display' => $this->format()];
    }

    /** @return array{string, int, string} */
    private function parts(): array
    {
        $absolute = abs($this->nano);
        $fraction = rtrim(str_pad((string) ($absolute % self::NANO_PER_DOLLAR), 9, '0', STR_PAD_LEFT), '0');

        return [
            $this->nano < 0 ? '-' : '',
            intdiv($absolute, self::NANO_PER_DOLLAR),
            str_pad($fraction, 2, '0'),
        ];
    }
}
