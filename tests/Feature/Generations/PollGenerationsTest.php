<?php

use App\Actions\Generations\ApplyGenerationUpdate;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const POLLED_ID = 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K';

function acceptedGeneration(int $quietSeconds = 60, string $providerId = POLLED_ID): Generation
{
    return Generation::factory()->processing()->create([
        'provider_job_id' => $providerId,
        'updated_at' => CarbonImmutable::now()->subSeconds($quietSeconds),
    ]);
}

function pixelforgeReports(string $fixture, int $status = 200, array $headers = []): void
{
    Http::fake(['pixelforge.test/v1/generations/*' => Http::response(pixelforgeFixture($fixture), $status, $headers)]);
}

it('applies a success the webhook never delivered', function () {
    pixelforgeReports('generation-succeeded');
    $generation = acceptedGeneration();

    $this->artisan('pixelforge:poll')->assertSuccessful();

    expect($generation->refresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->charge_nano?->nano)->toBe(381_887_500);
});

it('applies a failure the webhook never delivered', function () {
    pixelforgeReports('generation-failed');
    $generation = acceptedGeneration();

    $this->artisan('pixelforge:poll');

    expect($generation->refresh()->status)->toBe(GenerationStatus::Failed)
        ->and($generation->charge_nano?->nano)->toBe(0);
});

it('gives webhooks a head start before polling', function () {
    Http::fake();
    acceptedGeneration(quietSeconds: 5);

    $this->artisan('pixelforge:poll');

    Http::assertNothingSent();
});

it('does not charge twice when the webhook and the poller both report success', function () {
    pixelforgeReports('generation-succeeded');
    $generation = acceptedGeneration();
    app(ApplyGenerationUpdate::class)($generation, pixelforgeGeneration('generation-succeeded'));
    $generation->forceFill(['updated_at' => CarbonImmutable::now()->subMinute()])->save();

    $this->artisan('pixelforge:poll');

    Http::assertNothingSent();
    expect($generation->refresh()->charge_nano?->nano)->toBe(381_887_500);
});

it('moves a still-running generation to the back of the queue', function () {
    pixelforgeReports('generation-processing');
    $generation = acceptedGeneration();

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addSecond());
    $this->artisan('pixelforge:poll');

    expect($generation->refresh()->updated_at->greaterThan(CarbonImmutable::now()->subSeconds(2)))->toBeTrue();
});

it('stops for this tick when Pixelforge rate limits it', function () {
    pixelforgeReports('error-rate-limit', 429, ['Retry-After' => '20']);
    acceptedGeneration(providerId: 'gen_a');
    acceptedGeneration(providerId: 'gen_b');

    $this->artisan('pixelforge:poll')->assertSuccessful();

    Http::assertSentCount(1);
});

it('stops for this tick when Pixelforge cannot be reached', function () {
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('timed out');
    });
    acceptedGeneration(providerId: 'gen_a');
    acceptedGeneration(providerId: 'gen_b');

    $this->artisan('pixelforge:poll')->assertSuccessful();

    expect($attempts)->toBe(1);
});

it('cancels a generation Pixelforge still cannot find after 30 minutes, for free', function () {
    Http::fake(['pixelforge.test/v1/generations/*' => Http::response(pixelforgeFixture('error-not-found'), 404)]);
    $generation = Generation::factory()->processing()->create([
        'provider_job_id' => 'gen_missing',
        'submitted_at' => CarbonImmutable::now()->subMinutes(31),
        'updated_at' => CarbonImmutable::now()->subMinutes(2),
    ]);

    $this->artisan('pixelforge:poll');

    expect($generation->refresh()->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('not_found')
        ->and($generation->charge_nano?->nano)->toBe(0);
});

it('keeps going when applying one generation fails', function () {
    Http::fake([
        'pixelforge.test/v1/generations/gen_broken' => Http::response(pixelforgeFixture('generation-succeeded')),
        'pixelforge.test/v1/generations/*' => Http::response(pixelforgeFixture('generation-succeeded')),
    ]);
    $broken = acceptedGeneration(quietSeconds: 300, providerId: 'gen_broken');
    $healthy = acceptedGeneration(quietSeconds: 120);
    Queue::fake();
    $stale = Generation::factory()->create(['created_at' => CarbonImmutable::now()->subHour(), 'updated_at' => CarbonImmutable::now()->subHour()]);

    $this->artisan('pixelforge:poll')->assertSuccessful();

    expect($broken->refresh()->status)->toBe(GenerationStatus::Processing)
        ->and($broken->updated_at->greaterThan(CarbonImmutable::now()->subSeconds(5)))->toBeTrue()
        ->and($healthy->refresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($stale->refresh()->status)->toBe(GenerationStatus::Failed);
});

it('keeps going past a generation Pixelforge cannot find', function () {
    Http::fake([
        'pixelforge.test/v1/generations/gen_missing' => Http::response(pixelforgeFixture('error-not-found'), 404),
        'pixelforge.test/v1/generations/*' => Http::response(pixelforgeFixture('generation-succeeded')),
    ]);
    $missing = acceptedGeneration(quietSeconds: 120, providerId: 'gen_missing');
    $found = acceptedGeneration();

    $this->artisan('pixelforge:poll');

    expect($missing->refresh()->status)->toBe(GenerationStatus::Processing)
        ->and($found->refresh()->status)->toBe(GenerationStatus::Succeeded);
});

describe('generations Pixelforge never accepted', function () {
    beforeEach(fn () => Queue::fake());

    it('submits them again with the same idempotency key', function () {
        $generation = Generation::factory()->create(['updated_at' => CarbonImmutable::now()->subMinutes(3)]);

        $this->artisan('pixelforge:poll');

        Queue::assertPushed(SubmitGenerationJob::class, fn (SubmitGenerationJob $job) => $job->generation->is($generation));
    });

    it('leaves alone a submit that is still retrying', function () {
        Generation::factory()->create(['updated_at' => CarbonImmutable::now()->subSeconds(30)]);

        $this->artisan('pixelforge:poll');

        Queue::assertNothingPushed();
    });

    it('cancels them for free after half an hour', function () {
        $generation = Generation::factory()->create([
            'created_at' => CarbonImmutable::now()->subMinutes(31),
            'updated_at' => CarbonImmutable::now()->subMinutes(3),
        ]);

        $this->artisan('pixelforge:poll');

        expect($generation->refresh()->status)->toBe(GenerationStatus::Failed)
            ->and($generation->charge_nano?->nano)->toBe(0)
            ->and($generation->error_code)->toBe('submit_timeout');
        Queue::assertNothingPushed();
    });
});
