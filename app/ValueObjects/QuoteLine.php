<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\TokenType;
use JsonSerializable;

/** One row of the estimate breakdown, at provider cost (before markup). */
final readonly class QuoteLine implements JsonSerializable
{
    public function __construct(
        public TokenType $type,
        public TokenRange $tokens,
        public Money $ratePerMillion,
        public Money $minCost,
        public Money $maxCost,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'label' => $this->type->label(),
            'tokens' => ['min' => $this->tokens->min, 'max' => $this->tokens->max],
            'ratePerMillion' => $this->ratePerMillion,
            'cost' => ['min' => $this->minCost, 'max' => $this->maxCost],
        ];
    }
}
