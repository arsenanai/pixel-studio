<?php

declare(strict_types=1);

namespace App\Data\Pixelforge;

use App\Exceptions\InvalidPixelforgePayload;
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

    /** @return array<string, string> */
    public function stringMap(string $key): array
    {
        $map = [];

        foreach ($this->object($key)->data as $mapKey => $item) {
            if (! is_string($item)) {
                throw InvalidPixelforgePayload::at($this->pathTo($key).'.'.$mapKey, 'a string');
            }

            // json_decode turns numeric object keys into ints.
            $map[(string) $mapKey] = $item;
        }

        return $map;
    }

    /** @return array<array-key, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    private function pathTo(string $key): string
    {
        return "{$this->path}.{$key}";
    }
}
