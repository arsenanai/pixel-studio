<?php

declare(strict_types=1);

namespace App\Casts;

use App\Data\Pixelforge\Payload;
use App\ValueObjects\TokenRates;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** @implements CastsAttributes<TokenRates|null, TokenRates|null> */
final class TokenRatesCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?TokenRates
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("Column [{$key}] does not hold a rate snapshot.");
        }

        return TokenRates::fromUsdStrings(Payload::fromJson($value)->strings());
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : json_encode($value->toArray(), JSON_THROW_ON_ERROR);
    }
}
