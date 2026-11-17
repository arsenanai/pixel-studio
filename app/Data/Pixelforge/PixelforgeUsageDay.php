<?php

declare(strict_types=1);

namespace App\Data\Pixelforge;

use App\ValueObjects\Money;
use App\ValueObjects\TokenUsage;

final readonly class PixelforgeUsageDay
{
    public function __construct(
        public string $date,
        public int $generations,
        public TokenUsage $tokens,
        public Money $cost,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            date: $payload->string('date'),
            generations: $payload->int('generations'),
            tokens: $payload->tokenUsage(),
            cost: $payload->usd('cost_usd'),
        );
    }
}
