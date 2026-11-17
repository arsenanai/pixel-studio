<?php

use App\Actions\Generations\QuoteGeneration;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Enums\TokenType;
use App\ValueObjects\PriceQuote;
use App\ValueObjects\QuoteLine;
use App\ValueObjects\TokenRange;

function quote(string $prompt, ImageSize $size, ImageQuality $quality, int $references = 0): PriceQuote
{
    return (new QuoteGeneration(pricing(), 2_500))($prompt, $size, $quality, $references);
}

function line(PriceQuote $quote, TokenType $type): QuoteLine
{
    foreach ($quote->lines as $line) {
        if ($line->type === $type) {
            return $line;
        }
    }

    throw new RuntimeException("No {$type->value} line.");
}

// The docs' worked example: high, 1536x1024, 400-character prompt, 2 reference images.
function docsExample(): PriceQuote
{
    return quote(str_repeat('a', 400), ImageSize::Landscape, ImageQuality::High, 2);
}

it('reproduces the maximum cost from the docs', function () {
    $quote = docsExample();

    expect($quote->maxProviderCost->nano)->toBe(373_875_000)
        ->and($quote->maxCharge->nano)->toBe(467_343_750);
});

it('uses the fewest possible text and reasoning tokens for the minimum', function () {
    $quote = docsExample();

    // 85 text, 1,530 image, 6,450 output and 1,000 reasoning tokens.
    expect($quote->minProviderCost->nano)->toBe(286_225_000)
        ->and($quote->minCharge->nano)->toBe(357_781_250);
});

it('prices the typical case at the text estimate and midpoint reasoning', function () {
    $quote = docsExample();

    // 100 text, 1,530 image, 6,450 output and 4,500 reasoning tokens.
    expect($quote->typicalProviderCost->nano)->toBe(330_050_000)
        ->and($quote->typicalCharge->nano)->toBe(412_562_500);
});

it('knows output and reference image tokens exactly', function () {
    $quote = docsExample();

    expect(line($quote, TokenType::Output)->tokens)->toEqual(new TokenRange(6_450, 6_450))
        ->and(line($quote, TokenType::InputImage)->tokens)->toEqual(new TokenRange(1_530, 1_530))
        ->and(line($quote, TokenType::Reasoning)->tokens)->toEqual(new TokenRange(1_000, 8_000))
        ->and(line($quote, TokenType::InputText)->tokens)->toEqual(new TokenRange(85, 115));
});

it('has a breakdown that adds up to the provider cost', function () {
    $quote = docsExample();

    $min = array_sum(array_map(fn (QuoteLine $line) => $line->minCost->nano, $quote->lines));
    $max = array_sum(array_map(fn (QuoteLine $line) => $line->maxCost->nano, $quote->lines));

    expect($min)->toBe($quote->minProviderCost->nano)
        ->and($max)->toBe($quote->maxProviderCost->nano);
});

it('counts Unicode characters, not bytes', function () {
    // 8 characters but 16 bytes: two tokens, not four.
    $quote = quote(str_repeat('é', 8), ImageSize::Square, ImageQuality::Low);

    expect(line($quote, TokenType::InputText)->tokens->min)->toBe(1)
        ->and(line($quote, TokenType::InputText)->tokens->max)->toBe(3);
});

it('rounds the text token range outwards so it always contains the real count', function () {
    // ceil(28 / 4) = 7 tokens; 7 ± 15% is 5.95 to 8.05.
    $text = line(quote(str_repeat('a', 28), ImageSize::Square, ImageQuality::Low), TokenType::InputText);

    expect($text->tokens->min)->toBe(5)
        ->and($text->tokens->max)->toBe(9);
});

it('allows a low quality generation to use no reasoning at all', function () {
    $quote = quote('A lighthouse', ImageSize::Square, ImageQuality::Low);

    expect(line($quote, TokenType::Reasoning)->tokens->min)->toBe(0)
        ->and(line($quote, TokenType::InputImage)->minCost->isZero())->toBeTrue();
});

it('sends money as strings over the wire', function () {
    $json = json_decode((string) json_encode(docsExample()), true);

    expect($json['charge']['max'])->toBe(['nano' => '467343750', 'display' => '$0.46734375'])
        ->and($json['charge']['min']['display'])->toBe('$0.35778125')
        ->and($json['markupPercent'])->toBe(25)
        ->and($json['lines'])->toHaveCount(4);
});
