<?php

declare(strict_types=1);

namespace App\Actions\Generations;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Services\Pixelforge\PixelforgePricing;
use App\ValueObjects\PriceQuote;
use App\ValueObjects\TokenRange;
use App\ValueObjects\TokenUsage;
use Illuminate\Container\Attributes\Config;

/** Estimates a generation from Pixelforge's published token rules. */
final readonly class QuoteGeneration
{
    public function __construct(
        private PixelforgePricing $pricing,
        #[Config('billing.markup_basis_points')] private int $markupBasisPoints,
    ) {}

    public function __invoke(string $prompt, ImageSize $size, ImageQuality $quality, int $referenceImageCount): PriceQuote
    {
        $text = $this->textTokens($prompt);
        $images = $referenceImageCount * $this->pricing->tokensPerReferenceImage;
        $output = $this->pricing->outputTokens($quality, $size);
        $reasoning = $this->pricing->reasoningTokens($quality);

        return PriceQuote::calculate(
            rates: $this->pricing->rates,
            min: new TokenUsage($text['range']->min, $images, $output, $reasoning->min),
            typical: new TokenUsage($text['estimate'], $images, $output, $reasoning->midpoint()),
            max: new TokenUsage($text['range']->max, $images, $output, $reasoning->max),
            markupBasisPoints: $this->markupBasisPoints,
        );
    }

    /**
     * ceil(characters / 4), with the real count within ±15% of that. The bounds are
     * rounded outwards so the range always contains whatever Pixelforge counts.
     *
     * @return array{estimate: int, range: TokenRange}
     */
    private function textTokens(string $prompt): array
    {
        $perToken = $this->pricing->charactersPerTextToken;
        $variance = $this->pricing->textTokenVarianceBasisPoints;

        $estimate = intdiv(mb_strlen($prompt, 'UTF-8') + $perToken - 1, $perToken);

        return [
            'estimate' => $estimate,
            'range' => new TokenRange(
                intdiv($estimate * (10_000 - $variance), 10_000),
                intdiv($estimate * (10_000 + $variance) + 9_999, 10_000),
            ),
        ];
    }
}
