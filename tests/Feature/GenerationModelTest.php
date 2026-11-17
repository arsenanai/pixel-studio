<?php

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\ValueObjects\Money;
use App\ValueObjects\TokenUsage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('stores money as integer nanodollars and reads it back as Money', function () {
    $generation = Generation::factory()->succeeded(new TokenUsage(12, 765, 6_450, 3_184))->create();

    $row = DB::table('generations')->where('id', $generation->id)->first();

    expect((int) $row->provider_cost_nano)->toBe(305_510_000)
        ->and((int) $row->charge_nano)->toBe(381_887_500)
        ->and($generation->fresh()?->charge_nano)->toEqual(Money::fromNano(381_887_500));
});

it('stores the rate snapshot in Pixelforge format', function () {
    $generation = Generation::factory()->submitted()->create();

    $row = DB::table('generations')->where('id', $generation->id)->first();

    expect(json_decode($row->rates, true))->toBe([
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ])->and($generation->fresh()?->rates?->reasoning->nano)->toBe(12_500_000_000);
});

it('exposes usage only once every token count is known', function () {
    expect(Generation::factory()->processing()->create()->usage())->toBeNull()
        ->and(Generation::factory()->succeeded()->create()->usage()?->total())->toBe(2_210);
});

it('derives profit from charge and provider cost', function () {
    $generation = Generation::factory()->succeeded(new TokenUsage(12, 765, 6_450, 3_184))->create();

    expect($generation->profit()?->nano)->toBe(76_377_500)
        ->and(Generation::factory()->failed()->create()->profit()?->nano)->toBe(0)
        ->and(Generation::factory()->create()->profit())->toBeNull();
});

it('sends its own id as both the idempotency key and the metadata', function () {
    $generation = Generation::factory()->create();

    $request = $generation->toPixelforgeRequest();

    expect($request->idempotencyKey)->toBe($generation->id)
        ->and($request->metadata)->toBe(['generation_id' => $generation->id]);
});

it('refuses two generations with the same provider id', function () {
    Generation::factory()->submitted()->create(['provider_job_id' => 'gen_same']);
    Generation::factory()->submitted()->create(['provider_job_id' => 'gen_same']);
})->throws(UniqueConstraintViolationException::class);

it('refuses to store the same webhook event twice', function () {
    $insert = fn () => DB::table('webhook_events')->insert([
        'provider_event_id' => 'evt_same',
        'type' => 'generation.succeeded',
        'payload' => '{}',
        'received_at' => now(),
    ]);

    $insert();
    $insert();
})->throws(UniqueConstraintViolationException::class);

it('records when a webhook event was received', function () {
    $event = new WebhookEvent;
    $event->provider_event_id = 'evt_1';
    $event->type = 'ping';
    $event->payload = '{}';
    $event->save();

    expect($event->fresh()?->received_at)->not->toBeNull();
});
