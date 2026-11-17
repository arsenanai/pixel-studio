<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Data\Pixelforge\Payload;
use App\Enums\PixelforgeErrorCode;
use Illuminate\Http\Client\Response;
use RuntimeException;

final class PixelforgeRequestFailed extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly ?PixelforgeErrorCode $errorCode,
        string $message,
        public readonly ?string $param = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        $retryAfter = $response->header('Retry-After');
        $error = self::errorBody($response);

        return new self(
            status: $response->status(),
            errorCode: $error === null ? null : PixelforgeErrorCode::tryFrom($error->string('code')),
            message: $error?->string('message') ?? "Pixelforge responded with HTTP {$response->status()}.",
            param: $error?->nullableString('param'),
            retryAfterSeconds: ctype_digit($retryAfter) ? (int) $retryAfter : null,
        );
    }

    private static function errorBody(Response $response): ?Payload
    {
        try {
            $error = Payload::of($response->json())->object('error');
            $error->string('code');
            $error->string('message');

            return $error;
        } catch (InvalidPixelforgePayload) {
            return null;
        }
    }

    public function is(PixelforgeErrorCode $code): bool
    {
        return $this->errorCode === $code;
    }

    public function isRetryable(): bool
    {
        if ($this->is(PixelforgeErrorCode::DailyLimitExceeded)) {
            return false;
        }

        return $this->status === 429 || $this->status >= 500;
    }
}
