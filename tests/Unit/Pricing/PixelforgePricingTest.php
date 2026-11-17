<?php

use App\Data\Pixelforge\Payload;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Exceptions\InvalidPixelforgePayload;
use App\Services\Pixelforge\PixelforgePricing;

it('reads the token tables from GET /v1/pricing', function () {
    $pricing = pricing();

    expect($pricing->rates->reasoning->nano)->toBe(12_500_000_000)
        ->and($pricing->charactersPerTextToken)->toBe(4)
        ->and($pricing->textTokenVarianceBasisPoints)->toBe(1_500)
        ->and($pricing->tokensPerReferenceImage)->toBe(765)
        ->and($pricing->outputTokens(ImageQuality::Medium, ImageSize::Portrait))->toBe(1_650)
        ->and($pricing->reasoningTokens(ImageQuality::High)->max)->toBe(8_000);
});

it('rejects a pricing payload missing a size and quality combination', function () {
    $payload = json_decode(pixelforgeFixture('pricing'), true);
    unset($payload['tokens']['output']['high']['1024x1536']);

    PixelforgePricing::fromPayload(Payload::of($payload));
})->throws(InvalidPixelforgePayload::class, '[$.tokens.output.high.1024x1536] must be an integer');

it('rejects a rate sent as a number instead of an exact string', function () {
    $payload = json_decode(pixelforgeFixture('pricing'), true);
    $payload['rates']['output_tokens'] = 40.0;

    PixelforgePricing::fromPayload(Payload::of($payload));
})->throws(InvalidPixelforgePayload::class, '[$.rates.output_tokens] must be a string');
