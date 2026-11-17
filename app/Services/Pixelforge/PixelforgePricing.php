<?php

declare(strict_types=1);

namespace App\Services\Pixelforge;

use App\Data\Pixelforge\Payload;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\ValueObjects\TokenRange;
use App\ValueObjects\TokenRates;

/** The current rate card and token tables from GET /v1/pricing. */
final readonly class PixelforgePricing
{
    /**
     * @param  array<string, array<string, int>>  $outputTokens  quality => size => tokens
     * @param  array<string, TokenRange>  $reasoningTokens  quality => range
     */
    public function __construct(
        public TokenRates $rates,
        public int $charactersPerTextToken,
        public int $textTokenVarianceBasisPoints,
        public int $tokensPerReferenceImage,
        private array $outputTokens,
        private array $reasoningTokens,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        $tokens = $payload->object('tokens');
        $output = $tokens->object('output');
        $reasoning = $tokens->object('reasoning');

        $outputTokens = [];
        $reasoningTokens = [];

        // Read every combination up front, so a lookup can never miss later.
        foreach (ImageQuality::cases() as $quality) {
            foreach (ImageSize::cases() as $size) {
                $outputTokens[$quality->value][$size->value] = $output->object($quality->value)->int($size->value);
            }

            $range = $reasoning->object($quality->value);
            $reasoningTokens[$quality->value] = new TokenRange($range->int('min'), $range->int('max'));
        }

        return new self(
            rates: TokenRates::fromUsdStrings($payload->stringMap('rates')),
            charactersPerTextToken: $tokens->int('characters_per_text_token'),
            // A ratio like 0.15, not money; whole basis points keep the maths in integers.
            textTokenVarianceBasisPoints: (int) round($tokens->number('text_token_variance') * 10_000),
            tokensPerReferenceImage: $tokens->int('per_reference_image'),
            outputTokens: $outputTokens,
            reasoningTokens: $reasoningTokens,
        );
    }

    public function outputTokens(ImageQuality $quality, ImageSize $size): int
    {
        return $this->outputTokens[$quality->value][$size->value];
    }

    public function reasoningTokens(ImageQuality $quality): TokenRange
    {
        return $this->reasoningTokens[$quality->value];
    }
}
