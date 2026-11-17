<?php

use App\Data\Pixelforge\GenerationRequest;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Enums\PixelforgeErrorCode;
use App\Exceptions\InvalidPixelforgePayload;
use App\Exceptions\PixelforgeRequestFailed;
use App\Exceptions\PixelforgeUnreachable;
use App\Services\Pixelforge\PixelforgeClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function client(): PixelforgeClient
{
    return app(PixelforgeClient::class);
}

function generationRequest(array $metadata = ['generation_id' => '0193a6c4-7f7e-7c2b-9e43-1f0b6f7d2a10']): GenerationRequest
{
    return new GenerationRequest(
        idempotencyKey: '0193a6c4-7f7e-7c2b-9e43-1f0b6f7d2a10',
        prompt: 'A lighthouse on a cliff at dawn, oil painting',
        size: ImageSize::Landscape,
        quality: ImageQuality::High,
        referenceImages: ['https://example.com/style.png'],
        metadata: $metadata,
    );
}

function failWith(string $fixture, int $status, array $headers = []): void
{
    Http::fake(['*' => Http::response(pixelforgeFixture($fixture), $status, $headers)]);
}

/** @return PixelforgeRequestFailed */
function createFailure(): Throwable
{
    try {
        client()->createGeneration(generationRequest());
    } catch (PixelforgeRequestFailed $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected the request to fail.');
}

it('creates a generation with bearer auth, an idempotency key and only documented parameters', function () {
    Http::fake(['pixelforge.test/v1/generations' => Http::response(pixelforgeFixture('generation-queued'), 202)]);

    $generation = client()->createGeneration(generationRequest());

    expect($generation->status)->toBe(GenerationStatus::Queued);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer pf_test_key')
        && $request->hasHeader('Idempotency-Key', '0193a6c4-7f7e-7c2b-9e43-1f0b6f7d2a10')
        && $request->data() === [
            'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/style.png'],
            'metadata' => ['generation_id' => '0193a6c4-7f7e-7c2b-9e43-1f0b6f7d2a10'],
        ]);
});

it('sends empty metadata as a JSON object', function () {
    Http::fake(['*' => Http::response(pixelforgeFixture('generation-queued'), 202)]);

    client()->createGeneration(generationRequest(metadata: []));

    Http::assertSent(fn (Request $request) => str_contains($request->body(), '"metadata":{}'));
});

it('maps documented errors and whether they are worth retrying', function (string $fixture, int $status, array $headers, PixelforgeErrorCode $code, bool $retryable, ?int $retryAfter) {
    failWith($fixture, $status, $headers);

    $failure = createFailure();

    expect($failure->status)->toBe($status)
        ->and($failure->errorCode)->toBe($code)
        ->and($failure->isRetryable())->toBe($retryable)
        ->and($failure->retryAfterSeconds)->toBe($retryAfter);
})->with([
    'content policy' => ['error-content-policy', 400, [], PixelforgeErrorCode::ContentPolicyViolation, false, null],
    'validation' => ['error-validation', 422, [], PixelforgeErrorCode::ValidationFailed, false, null],
    'rate limit' => ['error-rate-limit', 429, ['Retry-After' => '12'], PixelforgeErrorCode::RateLimitExceeded, true, 12],
    'concurrency' => ['error-concurrency', 429, ['Retry-After' => '5'], PixelforgeErrorCode::ConcurrencyLimitExceeded, true, 5],
    'daily quota' => ['error-daily-limit', 429, [], PixelforgeErrorCode::DailyLimitExceeded, false, null],
    'overloaded' => ['error-overloaded', 503, ['Retry-After' => '2'], PixelforgeErrorCode::Overloaded, true, 2],
]);

it('keeps the parameter Pixelforge named in a validation error', function () {
    failWith('error-validation', 422);

    expect(createFailure()->param)->toBe('size');
});

it('treats an error page without a JSON body as a retryable failure', function () {
    Http::fake(['*' => Http::response('<html>Bad gateway</html>', 502)]);

    $failure = createFailure();

    expect($failure->errorCode)->toBeNull()
        ->and($failure->isRetryable())->toBeTrue();
});

it('reports a connection failure as unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    client()->createGeneration(generationRequest());
})->throws(PixelforgeUnreachable::class);

it('rejects a successful response it cannot read', function () {
    Http::fake(['*' => Http::response(['id' => 'gen_1'], 200)]);

    client()->generation('gen_1');
})->throws(InvalidPixelforgePayload::class);

it('retrieves a generation by its provider id', function () {
    Http::fake(['pixelforge.test/v1/generations/gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K' => Http::response(pixelforgeFixture('generation-succeeded'))]);

    expect(client()->generation('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')->status)->toBe(GenerationStatus::Succeeded);
});

it('asks for usage over an inclusive UTC date range', function () {
    Http::fake(['pixelforge.test/v1/usage*' => Http::response(pixelforgeFixture('usage'))]);

    $usage = client()->usage(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-02'));

    expect($usage->totalCost->nano)->toBe(169_080_000);
    Http::assertSent(fn (Request $request) => $request['start_date'] === '2026-11-01' && $request['end_date'] === '2026-11-02');
});

it('caches the public pricing between quotes', function () {
    Http::fake(['pixelforge.test/v1/pricing' => Http::response(pixelforgeFixture('pricing'))]);

    client()->pricing();
    $pricing = client()->pricing();

    expect($pricing->outputTokens(ImageQuality::High, ImageSize::Square))->toBe(4_300);
    Http::assertSentCount(1);
});

it('never reaches the real Pixelforge from a test', function () {
    client()->pricing();
})->throws(RuntimeException::class);
