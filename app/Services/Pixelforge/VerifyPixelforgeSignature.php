<?php

declare(strict_types=1);

namespace App\Services\Pixelforge;

use App\Exceptions\InvalidWebhookSignature;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use LogicException;

/** Checks `Pixelforge-Signature: t=<unix>,v1=<hex>` against the raw request body. */
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
            // A server error, not a 400, so Pixelforge keeps retrying until it is configured.
            throw new LogicException('PIXELFORGE_SIGNING_SECRET is not set.');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            match ($key) {
                't' => $timestamp = $value,
                // More than one v1 can be present while a secret is being rotated.
                'v1' => $signatures[] = $value,
                default => null,
            };
        }

        if ($timestamp === null || ! ctype_digit($timestamp) || $signatures === []) {
            throw new InvalidWebhookSignature('Malformed Pixelforge-Signature header.');
        }

        // Each delivery attempt is signed afresh, so an old timestamp means a replay.
        if (abs(CarbonImmutable::now()->getTimestamp() - (int) $timestamp) > $this->toleranceSeconds) {
            throw new InvalidWebhookSignature('Pixelforge-Signature timestamp is outside the tolerance window.');
        }

        // Signed over the raw body; re-encoded JSON would not match byte for byte.
        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $this->secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new InvalidWebhookSignature('Pixelforge-Signature does not match the body.');
    }
}
