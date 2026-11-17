<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\TokenType;
use JsonSerializable;

final readonly class PriceQuote implements JsonSerializable
{
    /** @param list<QuoteLine> $lines */
    private function __construct(
        public TokenRates $rates,
        public int $markupBasisPoints,
        public array $lines,
        public Money $minProviderCost,
        public Money $typicalProviderCost,
        public Money $maxProviderCost,
        public Money $minCharge,
        public Money $typicalCharge,
        public Money $maxCharge,
    ) {}

    public static function calculate(
        TokenRates $rates,
        TokenUsage $min,
        TokenUsage $typical,
        TokenUsage $max,
        int $markupBasisPoints,
    ): self {
        $lines = [];

        foreach (TokenType::cases() as $type) {
            $lines[] = new QuoteLine(
                type: $type,
                tokens: new TokenRange($min->tokens($type), $max->tokens($type)),
                ratePerMillion: $rates->rate($type),
                minCost: $rates->costOf($type, $min->tokens($type)),
                maxCost: $rates->costOf($type, $max->tokens($type)),
            );
        }

        $minProviderCost = $rates->cost($min);
        $typicalProviderCost = $rates->cost($typical);
        $maxProviderCost = $rates->cost($max);

        return new self(
            rates: $rates,
            markupBasisPoints: $markupBasisPoints,
            lines: $lines,
            minProviderCost: $minProviderCost,
            typicalProviderCost: $typicalProviderCost,
            maxProviderCost: $maxProviderCost,
            minCharge: $minProviderCost->withMarkup($markupBasisPoints),
            typicalCharge: $typicalProviderCost->withMarkup($markupBasisPoints),
            maxCharge: $maxProviderCost->withMarkup($markupBasisPoints),
        );
    }

    /**
     * @return array{
     *     lines: list<QuoteLine>,
     *     providerCost: array{min: Money, typical: Money, max: Money},
     *     markup: array{min: Money, typical: Money, max: Money},
     *     charge: array{min: Money, typical: Money, max: Money},
     *     markupPercent: int|float,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'lines' => $this->lines,
            'providerCost' => [
                'min' => $this->minProviderCost,
                'typical' => $this->typicalProviderCost,
                'max' => $this->maxProviderCost,
            ],
            'markup' => [
                'min' => $this->minCharge->subtract($this->minProviderCost),
                'typical' => $this->typicalCharge->subtract($this->typicalProviderCost),
                'max' => $this->maxCharge->subtract($this->maxProviderCost),
            ],
            'charge' => [
                'min' => $this->minCharge,
                'typical' => $this->typicalCharge,
                'max' => $this->maxCharge,
            ],
            'markupPercent' => $this->markupBasisPoints / 100,
        ];
    }
}
