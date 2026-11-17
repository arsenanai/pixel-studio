<?php

declare(strict_types=1);

namespace App\Enums;

enum PixelforgeErrorCode: string
{
    case InvalidApiKey = 'invalid_api_key';
    case ValidationFailed = 'validation_failed';
    case ContentPolicyViolation = 'content_policy_violation';
    case NotFound = 'not_found';
    case IdempotencyKeyReused = 'idempotency_key_reused';
    case RateLimitExceeded = 'rate_limit_exceeded';
    case ConcurrencyLimitExceeded = 'concurrency_limit_exceeded';
    case DailyLimitExceeded = 'daily_limit_exceeded';
    case Overloaded = 'overloaded';
    case DateRangeTooLong = 'date_range_too_long';
}
