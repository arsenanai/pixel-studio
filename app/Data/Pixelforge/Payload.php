<?php

declare(strict_types=1);

namespace App\Data\Pixelforge;

use App\Enums\TokenType;
use App\Exceptions\InvalidPixelforgePayload;
use App\ValueObjects\Money;
use App\ValueObjects\TokenRates;
use App\ValueObjects\TokenUsage;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;
use JsonException;

/**
 * Typed reads over decoded Pixelforge JSON. This is the only class that touches
 * `mixed`; everything it returns is checked, and a wrong shape names its path.
 */
final readonly class Payload
{
    /** @param array<array-key, mixed> $data */
    private function __construct(private array $data, private string $path) {}

    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidPixelforgePayload::at('$', 'valid JSON');
        }

        return self::of($decoded);
    }

    public static function of(mixed $value, string $path = '$'): self
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw InvalidPixelforgePayload::at($path, 'an object');
        }

        return new self($value, $path);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data) && $this->data[$key] !== null;
    }

    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;

        if (! is_string($value)) {
            throw InvalidPixelforgePayload::at($this->pathTo($key), 'a string');
        }

        return $value;
    }

    public function nullableString(string $key): ?string
    {
        return $this->has($key) ? $this->string($key) : null;
    }

    public function int(string $key): int
    {
        $value = $this->data[$key] ?? null;

        if (! is_int($value)) {
            throw InvalidPixelforgePayload::at($this->pathTo($key), 'an integer');
        }

        return $value;
    }

    public function nullableInt(string $key): ?int
    {
        return $this->has($key) ? $this->int($key) : null;
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T
     */
    public function enum(string $key, string $enum): BackedEnum
    {
        return $enum::tryFrom($this->string($key))
            ?? throw InvalidPixelforgePayload::at($this->pathTo($key), 'a known '.class_basename($enum));
    }

    public function timestamp(string $key): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->string($key))->utc();
        } catch (InvalidFormatException) {
            throw InvalidPixelforgePayload::at($this->pathTo($key), 'an ISO 8601 timestamp');
        }
    }

    public function nullableTimestamp(string $key): ?CarbonImmutable
    {
        return $this->has($key) ? $this->timestamp($key) : null;
    }

    public function usd(string $key): Money
    {
        try {
            return Money::fromUsdString($this->string($key));
        } catch (InvalidArgumentException) {
            throw InvalidPixelforgePayload::at($this->pathTo($key), 'an exact USD decimal string');
        }
    }

    /** Reads the four token counts Pixelforge uses in `usage` objects and usage reports. */
    public function tokenUsage(): TokenUsage
    {
        try {
            return new TokenUsage(
                $this->int(TokenType::InputText->value),
                $this->int(TokenType::InputImage->value),
                $this->int(TokenType::Output->value),
                $this->int(TokenType::Reasoning->value),
            );
        } catch (InvalidArgumentException) {
            throw InvalidPixelforgePayload::at($this->path, 'non-negative token counts');
        }
    }

    public function tokenRates(string $key): TokenRates
    {
        try {
            return TokenRates::fromUsdStrings($this->stringMap($key));
        } catch (InvalidArgumentException) {
            throw InvalidPixelforgePayload::at($this->pathTo($key), 'four exact USD rate strings');
        }
    }

    /** For dimensionless ratios only; money is always a decimal string. */
    public function number(string $key): int|float
    {
        $value = $this->data[$key] ?? null;

        if (! is_int($value) && ! is_float($value)) {
            throw InvalidPixelforgePayload::at($this->pathTo($key), 'a number');
        }

        return $value;
    }

    public function object(string $key): self
    {
        return self::of($this->data[$key] ?? null, $this->pathTo($key));
    }

    public function nullableObject(string $key): ?self
    {
        return $this->has($key) ? $this->object($key) : null;
    }

    /** @return list<string> */
    public function stringList(string $key): array
    {
        $value = $this->data[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidPixelforgePayload::at($this->pathTo($key), 'a list of strings');
        }

        $strings = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw InvalidPixelforgePayload::at($this->pathTo($key), 'a list of strings');
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /** @return list<self> */
    public function objectList(string $key): array
    {
        $value = $this->data[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidPixelforgePayload::at($this->pathTo($key), 'a list of objects');
        }

        $objects = [];

        foreach ($value as $index => $item) {
            $objects[] = self::of($item, "{$this->pathTo($key)}.{$index}");
        }

        return $objects;
    }

    /** @return array<string, string> */
    public function stringMap(string $key): array
    {
        return $this->object($key)->strings();
    }

    /**
     * This object read as a map of string values.
     *
     * @return array<string, string>
     */
    public function strings(): array
    {
        $map = [];

        foreach ($this->data as $key => $item) {
            if (! is_string($item)) {
                throw InvalidPixelforgePayload::at($this->pathTo((string) $key), 'a string');
            }

            // json_decode turns numeric object keys into ints.
            $map[(string) $key] = $item;
        }

        return $map;
    }

    private function pathTo(string $key): string
    {
        return "{$this->path}.{$key}";
    }
}
