<?php

use App\Data\Pixelforge\Payload;
use App\Data\Pixelforge\PixelforgeGeneration;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Exceptions\InvalidPixelforgePayload;

function generationFrom(array $payload): PixelforgeGeneration
{
    return PixelforgeGeneration::fromPayload(Payload::of($payload));
}

it('reads a queued generation', function () {
    $generation = generationFrom(pixelforgePayload('generation-queued'));

    expect($generation->id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')
        ->and($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->size)->toBe(ImageSize::Landscape)
        ->and($generation->quality)->toBe(ImageQuality::High)
        ->and($generation->referenceImages)->toBe(['https://example.com/style.png'])
        ->and($generation->metadata)->toBe(['order_id' => '1042'])
        ->and($generation->usage)->toBeNull()
        ->and($generation->rates?->reasoning->nano)->toBe(12_500_000_000)
        ->and($generation->createdAt->toIso8601ZuluString())->toBe('2026-11-04T14:03:11Z');
});

it('reads a succeeded generation with usage that prices exactly', function () {
    $generation = generationFrom(pixelforgePayload('generation-succeeded'));

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->usage?->reasoning)->toBe(3_184)
        ->and($generation->rates?->cost($generation->usage)->nano)->toBe(305_510_000)
        ->and($generation->imageUrl)->toStartWith('https://pixelforge.promptedit.dev/images/')
        ->and($generation->completedAt?->toIso8601ZuluString())->toBe('2026-11-04T14:03:29Z');
});

it('reads a failed generation with its error', function () {
    $generation = generationFrom(pixelforgePayload('generation-failed'));

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->errorCode)->toBe('internal_error')
        ->and($generation->errorMessage)->toContain('You were not charged')
        ->and($generation->usage)->toBeNull();
});

it('rejects a succeeded generation it could not price', function (array $missing) {
    generationFrom(pixelforgePayload('generation-succeeded', $missing));
})->with([
    'no usage' => [['usage' => null]],
    'no rates' => [['rates' => null]],
    'no completion time' => [['completed_at' => null]],
])->throws(InvalidPixelforgePayload::class);

it('rejects a failed generation without an error', function () {
    generationFrom(pixelforgePayload('generation-failed', ['error' => null]));
})->throws(InvalidPixelforgePayload::class, '[$.error]');

it('rejects a status it does not know', function () {
    generationFrom(pixelforgePayload('generation-queued', ['status' => 'cancelled']));
})->throws(InvalidPixelforgePayload::class, '[$.status] must be a known GenerationStatus');

it('rejects token counts that are not integers', function () {
    $payload = pixelforgePayload('generation-succeeded');
    $payload['usage']['output_tokens'] = '6450';

    generationFrom($payload);
})->throws(InvalidPixelforgePayload::class, '[$.usage.output_tokens] must be an integer');

it('keeps metadata keys as strings even when they look numeric', function () {
    $generation = generationFrom(pixelforgePayload('generation-queued', ['metadata' => ['7' => 'x']]));

    expect($generation->metadata)->toBe(['order_id' => '1042', '7' => 'x'])
        ->and($generation->metadataValue('7'))->toBe('x');
});
