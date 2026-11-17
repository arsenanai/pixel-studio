<?php

use App\Data\Pixelforge\Payload;
use App\Services\Pixelforge\PixelforgePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
