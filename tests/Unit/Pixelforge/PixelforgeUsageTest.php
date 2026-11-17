<?php

use App\Data\Pixelforge\Payload;
use App\Data\Pixelforge\PixelforgeUsage;
use App\Exceptions\InvalidPixelforgePayload;

it('reads exact per-day provider costs', function () {
    $usage = PixelforgeUsage::fromPayload(Payload::fromJson(pixelforgeFixture('usage')));

    expect($usage->days)->toHaveCount(2)
        ->and($usage->day('2026-11-01')->cost->nano)->toBe(169_080_000)
        ->and($usage->day('2026-11-01')->generations)->toBe(3)
        ->and($usage->day('2026-11-01')->tokens->reasoning)->toBe(2_950)
        ->and($usage->day('2026-11-02')->cost->isZero())->toBeTrue()
        ->and($usage->totalCost->nano)->toBe(169_080_000)
        ->and($usage->totalGenerations)->toBe(3);
});

it('rejects a cost that is not an exact decimal string', function () {
    $payload = pixelforgePayload('usage');
    $payload['data'][0]['cost_usd'] = 0.16908;

    PixelforgeUsage::fromPayload(Payload::of($payload));
})->throws(InvalidPixelforgePayload::class, '[$.data.0.cost_usd] must be a string');
