<?php

use App\Data\Pixelforge\Payload;
use App\Data\Pixelforge\PixelforgeGeneration;
use App\Services\Pixelforge\PixelforgePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Raw JSON of a Pixelforge response, copied verbatim from the API docs. */
function pixelforgeFixture(string $name): string
{
    $contents = file_get_contents(__DIR__."/Fixtures/Pixelforge/{$name}.json");

    if ($contents === false) {
        throw new RuntimeException("Missing fixture [{$name}].");
    }

    return $contents;
}

function pricing(): PixelforgePricing
{
    return PixelforgePricing::fromPayload(Payload::fromJson(pixelforgeFixture('pricing')));
}

/**
 * A fixture decoded to an array, with fields replaced for the case under test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pixelforgePayload(string $name, array $overrides = []): array
{
    return array_replace_recursive(json_decode(pixelforgeFixture($name), true), $overrides);
}

/** @param array<string, mixed> $overrides */
function pixelforgeGeneration(string $fixture, array $overrides = []): PixelforgeGeneration
{
    return PixelforgeGeneration::fromPayload(
        Payload::of(pixelforgePayload($fixture, $overrides)),
    );
}

/** Serves the documented pricing for quotes. */
function fakePricing(): void
{
    Http::fake([
        'pixelforge.test/v1/pricing' => Http::response(pixelforgeFixture('pricing')),
    ]);
}
