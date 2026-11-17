<?php

use App\Data\Pixelforge\Payload;
use App\Data\Pixelforge\PixelforgeEvent;
use App\Enums\GenerationStatus;
use App\Exceptions\InvalidPixelforgePayload;

it('reads a generation event and the generation inside it', function () {
    $event = PixelforgeEvent::fromPayload(Payload::fromJson(pixelforgeFixture('event-succeeded')));

    expect($event->id)->toBe('evt_01JC2Z9B7K2M4P6R8T0V3X5Z7A')
        ->and($event->type)->toBe('generation.succeeded')
        ->and($event->generation?->status)->toBe(GenerationStatus::Succeeded)
        ->and($event->generation?->usage?->total())->toBe(10_411);
});

it('reads a ping, which carries no generation', function (array $payload) {
    $event = PixelforgeEvent::fromPayload(Payload::of($payload));

    expect($event->type)->toBe(PixelforgeEvent::PING)
        ->and($event->generation)->toBeNull();
})->with([
    'empty data' => [pixelforgePayload('event-ping')],
    'no data at all' => [array_diff_key(pixelforgePayload('event-ping'), ['data' => true])],
]);

it('rejects a body that is not JSON', function () {
    Payload::fromJson('{"id": "evt_');
})->throws(InvalidPixelforgePayload::class, 'valid JSON');

it('rejects a JSON list where an object belongs', function () {
    Payload::fromJson('[1, 2]');
})->throws(InvalidPixelforgePayload::class, 'must be an object');
