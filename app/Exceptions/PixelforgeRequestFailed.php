<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Data\Pixelforge\Payload;
use App\Enums\PixelforgeErrorCode;
use Illuminate\Http\Client\Response;
use RuntimeException;

/** Pixelforge answered with an error status. Rejected requests create and bill nothing. */
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
        $code = null;
        $message = "Pixelforge responded with HTTP {$response->status()}.";
        $param = null;

        try {
            $error = Payload::of($response->json())->object('error');
            $code = PixelforgeErrorCode::tryFrom($error->string('code'));
            $message = $error->string('message');
            $param = $error->nullableString('param');
        } catch (InvalidPixelforgePayload) {
            // A proxy or load balancer error page; the status code is all we have.
        }

        return new self(
            status: $response->status(),
            errorCode: $code,
            message: $message,
            param: $param,
            retryAfterSeconds: ctype_digit($retryAfter) ? (int) $retryAfter : null,
        );
    }

    public function is(PixelforgeErrorCode $code): bool
    {
        return $this->errorCode === $code;
    }

    /** Worth sending the same request again later. */
    public function isRetryable(): bool
    {
        // The daily quota resets at midnight UTC; retrying within minutes cannot help.
        if ($this->is(PixelforgeErrorCode::DailyLimitExceeded)) {
            return false;
        }

        return $this->status === 429 || $this->status >= 500;
    }
}
