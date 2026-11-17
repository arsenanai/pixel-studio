<?php

use App\Actions\Generations\SubmitGeneration;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

const SECRET = 'whsec_test_secret';

function sign(string $body, ?int $timestamp = null, string $secret = SECRET): string
{
    $timestamp ??= CarbonImmutable::now()->getTimestamp();

    return "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
}

/** POSTs exact bytes, as Pixelforge does, so the signature covers what the app reads. */
function deliverRaw(string $body, ?string $signature, array $headers = []): TestResponse
{
    $server = ['CONTENT_TYPE' => 'application/json'];

    if ($signature !== null) {
        $server['HTTP_PIXELFORGE_SIGNATURE'] = $signature;
    }

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call('POST', '/api/pixelforge/webhook', server: $server, content: $body);
}

/** An event fixture, addressed to one of our generations through its metadata. */
function eventFor(?Generation $generation, string $fixture, array $overrides = []): string
{
    $payload = pixelforgePayload($fixture, $overrides);

    if ($generation !== null) {
        $payload['data']['object']['metadata'] = ['generation_id' => $generation->id];
    }

    return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function deliver(string $body, array $headers = []): TestResponse
{
    return deliverRaw($body, sign($body), $headers);
}

describe('signatures', function () {
    it('accepts an event signed over the raw body', function () {
        $body = eventFor(Generation::factory()->create(), 'event-processing');

        deliver($body)->assertNoContent();
    });

    it('rejects a request it cannot authenticate', function (Closure $signature) {
        $generation = Generation::factory()->create();
        $body = eventFor($generation, 'event-succeeded');

        deliverRaw($body, $signature($body))->assertStatus(400);

        expect(WebhookEvent::count())->toBe(0)
            ->and($generation->refresh()->status)->toBe(GenerationStatus::Queued);
    })->with([
        'no signature' => [fn () => null],
        'garbage header' => [fn () => 'nonsense'],
        'no timestamp' => [fn (string $body) => 'v1='.hash_hmac('sha256', $body, SECRET)],
        'wrong secret' => [fn (string $body) => sign($body, secret: 'whsec_someone_else')],
        'tampered body' => [fn (string $body) => sign(str_replace('6450', '1', $body))],
        'older than five minutes' => [fn (string $body) => sign($body, CarbonImmutable::now()->subSeconds(301)->getTimestamp())],
        'from the future' => [fn (string $body) => sign($body, CarbonImmutable::now()->addSeconds(301)->getTimestamp())],
    ]);

    it('accepts a timestamp right at the edge of the window', function () {
        $body = eventFor(Generation::factory()->create(), 'event-processing');

        deliverRaw($body, sign($body, CarbonImmutable::now()->subSeconds(300)->getTimestamp()))->assertNoContent();
    });

    it('accepts any matching signature while a secret is rotated', function () {
        $body = eventFor(Generation::factory()->create(), 'event-processing');
        $valid = sign($body);
        $old = explode(',', sign($body, secret: 'whsec_old'))[1];

        deliverRaw($body, "{$valid},{$old}")->assertNoContent();
    });

    it('rejects a validly signed body that is not JSON', function () {
        deliver('{"id": "evt_')->assertStatus(400);
    });

    it('fails loudly, so Pixelforge retries, when no signing secret is configured', function () {
        config(['services.pixelforge.signing_secret' => '']);
        $body = eventFor(Generation::factory()->create(), 'event-processing');

        deliver($body)->assertServerError();
    });
});

describe('applying events', function () {
    it('records the exact charge when a generation succeeds', function () {
        $generation = Generation::factory()->create();

        deliver(eventFor($generation, 'event-succeeded'), ['Pixelforge-Delivery-Attempt' => '1'])->assertNoContent();

        $generation->refresh();
        expect($generation->status)->toBe(GenerationStatus::Succeeded)
            ->and($generation->provider_cost_nano?->nano)->toBe(305_510_000)
            ->and($generation->charge_nano?->nano)->toBe(381_887_500);

        $event = WebhookEvent::sole();
        expect($event->provider_event_id)->toBe('evt_01JC2Z9B7K2M4P6R8T0V3X5Z7A')
            ->and($event->generation_id)->toBe($generation->id)
            ->and($event->delivery_attempt)->toBe(1);
    });

    it('charges nothing when a generation fails', function () {
        $generation = Generation::factory()->create();

        deliver(eventFor($generation, 'event-failed'))->assertNoContent();

        expect($generation->refresh()->status)->toBe(GenerationStatus::Failed)
            ->and($generation->charge_nano?->nano)->toBe(0);
    });

    it('stores the exact bytes it verified', function () {
        $body = eventFor(Generation::factory()->create(), 'event-succeeded');

        deliver($body);

        expect(WebhookEvent::sole()->payload)->toBe($body);
    });

    it('finds the generation by provider id when the metadata is missing', function () {
        $generation = Generation::factory()->submitted()->create(['provider_job_id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K']);

        deliver(eventFor(null, 'event-succeeded'))->assertNoContent();

        expect($generation->refresh()->status)->toBe(GenerationStatus::Succeeded);
    });

    it('acknowledges a ping without touching any generation', function () {
        deliver(pixelforgeFixture('event-ping'))->assertNoContent();

        expect(WebhookEvent::sole()->type)->toBe('ping')
            ->and(WebhookEvent::sole()->generation_id)->toBeNull();
    });

    it('acknowledges an event for a generation it does not know, and logs it', function () {
        Log::spy();

        deliver(eventFor(null, 'event-succeeded'))->assertNoContent();

        Log::shouldHaveReceived('warning')->with('pixelforge.webhook.unknown_generation', Mockery::any())->once();
    });

    it('rejects a succeeded event it could not price, so a later delivery or poll can', function () {
        $generation = Generation::factory()->create();

        deliver(eventFor($generation, 'event-succeeded', ['data' => ['object' => ['usage' => null]]]))->assertStatus(400);

        expect(WebhookEvent::count())->toBe(0)
            ->and($generation->refresh()->status)->toBe(GenerationStatus::Queued);
    });
});

describe('duplicates and ordering', function () {
    it('does not record a second charge for a redelivered event', function () {
        $generation = Generation::factory()->create();
        $body = eventFor($generation, 'event-succeeded');

        deliver($body, ['Pixelforge-Delivery-Attempt' => '1'])->assertNoContent();
        deliver($body, ['Pixelforge-Delivery-Attempt' => '2'])->assertNoContent();

        expect(WebhookEvent::count())->toBe(1)
            ->and($generation->refresh()->charge_nano?->nano)->toBe(381_887_500);
    });

    it('does not record a second charge for a different event with the same outcome', function () {
        $generation = Generation::factory()->create();

        deliver(eventFor($generation, 'event-succeeded'))->assertNoContent();
        deliver(eventFor($generation, 'event-succeeded', [
            'id' => 'evt_second_delivery',
            'data' => ['object' => ['usage' => ['reasoning_tokens' => 8_000]]],
        ]))->assertNoContent();

        expect(WebhookEvent::count())->toBe(2)
            ->and($generation->refresh()->charge_nano?->nano)->toBe(381_887_500);
    });

    it('ignores a processing event retried after the generation succeeded', function () {
        $generation = Generation::factory()->create();

        deliver(eventFor($generation, 'event-succeeded'));
        deliver(eventFor($generation, 'event-processing'))->assertNoContent();

        expect($generation->refresh()->status)->toBe(GenerationStatus::Succeeded);
    });

    it('applies success when processing never arrived', function () {
        $generation = Generation::factory()->create();

        deliver(eventFor($generation, 'event-succeeded'));

        expect($generation->refresh()->status)->toBe(GenerationStatus::Succeeded);
    });

    it('keeps the first outcome when a contradicting one arrives later', function () {
        $generation = Generation::factory()->create();

        deliver(eventFor($generation, 'event-failed'));
        deliver(eventFor($generation, 'event-succeeded'));

        expect($generation->refresh()->status)->toBe(GenerationStatus::Failed)
            ->and($generation->charge_nano?->nano)->toBe(0);
    });

    it('dedupes on the signed body id and logs when the header disagrees', function () {
        Log::spy();
        $body = eventFor(Generation::factory()->create(), 'event-processing');

        deliver($body, ['Pixelforge-Event-Id' => 'evt_header_only'])->assertNoContent();
        deliver($body, ['Pixelforge-Event-Id' => 'evt_another'])->assertNoContent();

        expect(WebhookEvent::sole()->provider_event_id)->toBe('evt_01JC2Z8ZP3E5G7J9L1N3Q5S7U9');
        Log::shouldHaveReceived('warning')->with('pixelforge.webhook.event_id_mismatch', Mockery::any())->twice();
    });

    it('handles a webhook that arrives before the create call returns', function () {
        $generation = Generation::factory()->create();

        Http::fake(function () use ($generation) {
            // Pixelforge finishes and calls back while our POST is still in flight...
            deliver(eventFor($generation, 'event-succeeded'))->assertNoContent();

            // ...then the 202 for that POST, still saying queued, finally lands.
            return Http::response(
                pixelforgePayload('generation-queued', ['metadata' => ['generation_id' => $generation->id]]),
                202,
            );
        });

        (new SubmitGenerationJob($generation))->withFakeQueueInteractions()->handle(app(SubmitGeneration::class));

        Http::assertSentCount(1);
        expect($generation->refresh()->status)->toBe(GenerationStatus::Succeeded)
            ->and($generation->charge_nano?->nano)->toBe(381_887_500)
            ->and($generation->provider_job_id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K');
    });
});
