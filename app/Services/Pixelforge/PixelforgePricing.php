<?php

declare(strict_types=1);

namespace App\Services\Pixelforge;

use App\Data\Pixelforge\Payload;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\ValueObjects\TokenRange;
use App\ValueObjects\TokenRates;

final readonly class PixelforgePricing
{
    /**
     * @param  array<string, array<string, int>>  $outputTokens
     * @param  array<string, TokenRange>  $reasoningTokens
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

        foreach (ImageQuality::cases() as $quality) {
            foreach (ImageSize::cases() as $size) {
                $outputTokens[$quality->value][$size->value] = $output->object($quality->value)->int($size->value);
            }

            $range = $reasoning->object($quality->value);
            $reasoningTokens[$quality->value] = new TokenRange($range->int('min'), $range->int('max'));
        }

        return new self(
            rates: $payload->tokenRates('rates'),
            charactersPerTextToken: $tokens->int('characters_per_text_token'),
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
