<?php

use App\Actions\Generations\ApplyGenerationUpdate;
use App\Actions\Generations\SubmitGeneration;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function runSubmitJob(Generation $generation): SubmitGenerationJob
{
    $job = (new SubmitGenerationJob($generation))->withFakeQueueInteractions();
    $job->handle(app(SubmitGeneration::class));

    return $job;
}

function pixelforgeAnswers(string $fixture, int $status, array $headers = [], array $overrides = []): void
{
    Http::fake(['pixelforge.test/v1/generations' => Http::response(pixelforgePayload($fixture, $overrides), $status, $headers)]);
}

it('records the provider id and rate snapshot when Pixelforge accepts the generation', function () {
    pixelforgeAnswers('generation-queued', 202);
    $generation = Generation::factory()->create();

    runSubmitJob($generation)->assertNotReleased();

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->provider_job_id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')
        ->and($generation->rates?->reasoning->nano)->toBe(12_500_000_000)
        ->and($generation->submit_attempts)->toBe(1)
        ->and($generation->submitted_at)->not->toBeNull();
});

it('sends our id as the idempotency key and in the metadata', function () {
    pixelforgeAnswers('generation-queued', 202);
    $generation = Generation::factory()->create();

    runSubmitJob($generation);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', $generation->id)
        && $request['metadata'] === ['generation_id' => $generation->id]
        && $request['prompt'] === $generation->prompt);
});

it('applies a finished generation from an idempotent replay', function () {
    pixelforgeAnswers('generation-succeeded', 202, ['Idempotent-Replayed' => 'true']);
    $generation = Generation::factory()->create();

    runSubmitJob($generation);

    expect($generation->refresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->charge_nano?->nano)->toBe(381_887_500);
});

it('does not submit again once a webhook has moved the generation on', function () {
    Http::fake();
    $generation = Generation::factory()->create();
    app(ApplyGenerationUpdate::class)($generation, pixelforgeGeneration('generation-succeeded'));

    runSubmitJob($generation->refresh());

    Http::assertNothingSent();
    expect($generation->refresh()->charge_nano?->nano)->toBe(381_887_500);
});

it('fails the generation for free when Pixelforge rejects it outright', function (string $fixture, int $status, string $code, string $reason) {
    pixelforgeAnswers($fixture, $status);
    $generation = Generation::factory()->create();

    runSubmitJob($generation)->assertNotReleased();

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->charge_nano?->nano)->toBe(0)
        ->and($generation->provider_cost_nano?->nano)->toBe(0)
        ->and($generation->error_code)->toBe($code)
        ->and($generation->error_message)->toContain($reason);
})->with([
    'content policy' => ['error-content-policy', 400, 'content_policy_violation', 'content policy'],
    'validation' => ['error-validation', 422, 'validation_failed', 'The selected size is invalid.'],
    'daily quota' => ['error-daily-limit', 429, 'daily_limit_exceeded', 'midnight UTC'],
]);

it('waits as long as Pixelforge asks when it is at capacity', function (string $fixture, int $status, string $retryAfter, int $delay) {
    pixelforgeAnswers($fixture, $status, ['Retry-After' => $retryAfter]);
    $generation = Generation::factory()->create();

    runSubmitJob($generation)->assertReleased(delay: $delay);

    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued)
        ->and($generation->charge_nano)->toBeNull();
})->with([
    'concurrency limit' => ['error-concurrency', 429, '5', 5],
    'rate limit' => ['error-rate-limit', 429, '30', 30],
    // Never sooner than our own minimum backoff.
    'overloaded' => ['error-overloaded', 503, '2', 5],
]);

it('retries a timeout with the same idempotency key and parameters', function () {
    $sent = [];
    Http::fake(function (Request $request) use (&$sent) {
        $sent[] = [$request->header('Idempotency-Key'), $request->body()];

        if (count($sent) === 1) {
            throw new ConnectionException('cURL error 28: Operation timed out');
        }

        return Http::response(pixelforgeFixture('generation-queued'), 202, ['Idempotent-Replayed' => 'true']);
    });
    $generation = Generation::factory()->create();

    runSubmitJob($generation)->assertReleased(delay: 5);
    runSubmitJob($generation->refresh());

    expect($sent)->toHaveCount(2)
        ->and($sent[0])->toBe($sent[1])
        ->and($generation->refresh()->provider_job_id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')
        ->and($generation->submit_attempts)->toBe(2);
});
