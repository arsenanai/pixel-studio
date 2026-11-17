<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final readonly class Money implements JsonSerializable
{
    public const NANO_PER_DOLLAR = 1_000_000_000;

    private function __construct(public int $nano) {}

    public static function fromNano(int $nano): self
    {
        return new self($nano);
    }

    public static function fromDatabase(mixed $value): self
    {
        if (is_int($value)) {
            return new self($value);
        }

        if (is_string($value) && preg_match('/^-?\d{1,19}$/', $value) === 1) {
            return new self((int) $value);
        }

        throw new InvalidArgumentException('Expected a whole number of nanodollars from the database.');
    }

    public static function zero(): self
    {
        return new self(0);
    }

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

    public function toDecimalString(): string
    {
        [$sign, $dollars, $fraction] = $this->parts();

        return "{$sign}{$dollars}.{$fraction}";
    }

    public function format(): string
    {
        [$sign, $dollars, $fraction] = $this->parts();

        return $sign.'$'.number_format($dollars).'.'.$fraction;
    }

    /** @return array{nano: string, display: string} */
    public function jsonSerialize(): array
    {
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
