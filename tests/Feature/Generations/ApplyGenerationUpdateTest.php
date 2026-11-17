<?php

use App\Actions\Generations\ApplyGenerationUpdate;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

const PROVIDER_ID = 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K';

function apply(Generation $generation, string $fixture, array $overrides = []): Generation
{
    return app(ApplyGenerationUpdate::class)($generation, pixelforgeGeneration($fixture, $overrides));
}

function queuedGeneration(): Generation
{
    return Generation::factory()->create();
}

it('stores the provider id and rate snapshot from the create response without changing status', function () {
    $generation = apply(queuedGeneration(), 'generation-queued');

    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->provider_job_id)->toBe(PROVIDER_ID)
        ->and($generation->rates?->output->nano)->toBe(40_000_000_000);
});

it('moves a queued generation to processing', function () {
    $generation = apply(queuedGeneration(), 'generation-processing');

    expect($generation->status)->toBe(GenerationStatus::Processing)
        ->and($generation->started_at?->toIso8601ZuluString())->toBe('2026-11-04T14:03:13Z');
});

it('records the exact cost and charge when a generation succeeds', function () {
    $generation = queuedGeneration();
    apply($generation, 'generation-processing');

    $generation = apply($generation, 'generation-succeeded');

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->usage()?->reasoning)->toBe(3_184)
        ->and($generation->provider_cost_nano?->nano)->toBe(305_510_000)
        ->and($generation->charge_nano?->nano)->toBe(381_887_500)
        ->and($generation->image_url)->toStartWith('https://pixelforge.promptedit.dev/images/');
});

it('takes the completion time from Pixelforge, which is the day it bills on', function () {
    CarbonImmutable::setTestNow('2026-11-05 00:00:09');

    $generation = apply(queuedGeneration(), 'generation-succeeded');

    expect($generation->completed_at?->toIso8601ZuluString())->toBe('2026-11-04T14:03:29Z');
});

it('applies a success that arrives before processing was ever seen', function () {
    $generation = apply(queuedGeneration(), 'generation-succeeded');

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->started_at?->toIso8601ZuluString())->toBe('2026-11-04T14:03:13Z');
});

it('does not record a second charge for a duplicate succeeded snapshot', function () {
    $generation = apply(queuedGeneration(), 'generation-succeeded');
    $updatedAt = $generation->updated_at;

    CarbonImmutable::setTestNow(now()->addMinute());
    $again = apply($generation, 'generation-succeeded', [
        'usage' => ['reasoning_tokens' => 8_000],
    ]);

    expect($again->charge_nano?->nano)->toBe(381_887_500)
        ->and($again->updated_at)->toEqual($updatedAt);
});

it('ignores a processing event that arrives after the generation finished', function (string $final) {
    $generation = apply(queuedGeneration(), $final);

    $generation = apply($generation, 'generation-processing');

    expect($generation->status->isTerminal())->toBeTrue();
})->with(['generation-succeeded', 'generation-failed']);

it('keeps the first outcome when the other one arrives later', function (string $first, string $second, GenerationStatus $kept) {
    $generation = apply(queuedGeneration(), $first);

    expect(apply($generation, $second)->status)->toBe($kept);
})->with([
    'failed after succeeded' => ['generation-succeeded', 'generation-failed', GenerationStatus::Succeeded],
    'succeeded after failed' => ['generation-failed', 'generation-succeeded', GenerationStatus::Failed],
]);

it('charges nothing for a failed generation', function () {
    $generation = apply(queuedGeneration(), 'generation-failed');

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->provider_cost_nano?->nano)->toBe(0)
        ->and($generation->charge_nano?->nano)->toBe(0)
        ->and($generation->error_code)->toBe('internal_error')
        ->and($generation->error_message)->toContain('You were not charged');
});

it('bills at the rates fixed on the generation, not current pricing', function () {
    $generation = apply(queuedGeneration(), 'generation-succeeded', [
        'rates' => ['output_tokens' => '80.00'],
    ]);

    expect($generation->provider_cost_nano?->nano)->toBe(563_510_000)
        ->and($generation->rates?->output->nano)->toBe(80_000_000_000);
});

it('does not regress when the create response arrives after the webhook', function () {
    $generation = apply(queuedGeneration(), 'generation-succeeded');

    $generation = apply($generation, 'generation-queued');

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->charge_nano?->nano)->toBe(381_887_500);
});

it('refuses a snapshot for a different provider generation', function () {
    $generation = Generation::factory()->submitted()->create(['provider_job_id' => 'gen_other']);

    apply($generation, 'generation-succeeded');
})->throws(LogicException::class);

it('warns when the actual charge is above the estimate shown to the user', function () {
    Log::spy();
    $generation = Generation::factory()->create(['estimated_max_charge_nano' => Money::fromNano(100)]);

    apply($generation, 'generation-succeeded');

    Log::shouldHaveReceived('warning')->with('pixelforge.pricing.above_estimate', Mockery::any())->once();
});

describe('failures before Pixelforge accepted the request', function () {
    it('fails a queued generation at no cost', function () {
        $generation = app(ApplyGenerationUpdate::class)->failBeforeAcceptance(
            queuedGeneration(), 'content_policy_violation', 'The prompt was rejected.',
        );

        expect($generation->status)->toBe(GenerationStatus::Failed)
            ->and($generation->charge_nano?->nano)->toBe(0)
            ->and($generation->error_code)->toBe('content_policy_violation');
    });

    it('leaves a generation alone once Pixelforge has accepted it', function () {
        $generation = app(ApplyGenerationUpdate::class)->failBeforeAcceptance(
            Generation::factory()->submitted()->create(), 'unreachable', 'Gave up.',
        );

        expect($generation->status)->toBe(GenerationStatus::Queued);
    });
});
