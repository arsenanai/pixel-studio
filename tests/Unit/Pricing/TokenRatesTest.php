<?php

use App\Enums\TokenType;
use App\ValueObjects\TokenRates;
use App\ValueObjects\TokenUsage;

function publishedRates(): TokenRates
{
    return TokenRates::fromUsdStrings([
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ]);
}

it('prices the succeeded generation from the docs exactly', function () {
    $cost = publishedRates()->cost(new TokenUsage(12, 765, 6_450, 3_184));

    expect($cost->nano)->toBe(305_510_000)
        ->and($cost->format())->toBe('$0.30551');
});

it('prices the most expensive generation from the docs exactly', function () {
    expect(publishedRates()->cost(new TokenUsage(115, 1_530, 6_450, 8_000))->nano)->toBe(373_875_000);
});

it('prices a single token type', function () {
    expect(publishedRates()->costOf(TokenType::Reasoning, 3_184)->nano)->toBe(39_800_000);
});

it('round-trips the rate snapshot in Pixelforge format', function () {
    expect(publishedRates()->toArray())->toBe([
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ]);
});

it('rejects a rate card with a missing token type', function () {
    TokenRates::fromUsdStrings(['input_text_tokens' => '5.00']);
})->throws(InvalidArgumentException::class);

it('refuses to round a cost that falls between two nanodollars', function () {
    $rates = TokenRates::fromUsdStrings([
        'input_text_tokens' => '0.000000100',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ]);

    $rates->cost(new TokenUsage(1, 0, 0, 0));
})->throws(UnexpectedValueException::class);

it('does not accept negative token counts', function () {
    new TokenUsage(-1, 0, 0, 0);
})->throws(InvalidArgumentException::class);
