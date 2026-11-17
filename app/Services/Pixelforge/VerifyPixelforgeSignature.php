<?php

declare(strict_types=1);

namespace App\Services\Pixelforge;

use App\Exceptions\InvalidWebhookSignature;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use LogicException;

final readonly class VerifyPixelforgeSignature
{
    public function __construct(
        #[Config('services.pixelforge.signing_secret')] private string $secret,
        #[Config('services.pixelforge.webhook_tolerance')] private int $toleranceSeconds,
    ) {}

    /** @throws InvalidWebhookSignature */
    public function __invoke(string $header, string $rawBody): void
    {
        if ($this->secret === '') {
            throw new LogicException('PIXELFORGE_SIGNING_SECRET is not set.');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            match ($key) {
                't' => $timestamp = $value,
                'v1' => $signatures[] = $value,
                default => null,
            };
        }

        if ($timestamp === null || ! ctype_digit($timestamp) || $signatures === []) {
            throw new InvalidWebhookSignature('Malformed Pixelforge-Signature header.');
        }

        if (abs(CarbonImmutable::now()->getTimestamp() - (int) $timestamp) > $this->toleranceSeconds) {
            throw new InvalidWebhookSignature('Pixelforge-Signature timestamp is outside the tolerance window.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $this->secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new InvalidWebhookSignature('Pixelforge-Signature does not match the body.');
    }
}
