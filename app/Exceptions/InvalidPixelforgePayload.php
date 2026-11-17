<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidPixelforgePayload extends RuntimeException
{
    public static function at(string $path, string $expected): self
    {
        return new self("Pixelforge payload field [{$path}] must be {$expected}.");
    }
}
