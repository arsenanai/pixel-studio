<?php

declare(strict_types=1);

namespace App\Actions\Generations;

use App\Data\Pixelforge\PixelforgeGeneration;
use App\Enums\GenerationStatus;
use App\Exceptions\InvalidPixelforgePayload;
use App\Models\Generation;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * The only writer of a generation's status and cost. The webhook, the poller and
 * the submit job all go through here, in any order and any number of times.
 */
final readonly class ApplyGenerationUpdate
{
    public function __construct(
        #[Config('billing.markup_basis_points')] private int $markupBasisPoints,
    ) {}

    public function __invoke(Generation $generation, PixelforgeGeneration $snapshot): Generation
    {
        return $this->locked($generation, function (Generation $row) use ($snapshot): void {
            if ($row->provider_job_id !== null && $row->provider_job_id !== $snapshot->id) {
                throw new LogicException("Generation [{$row->id}] belongs to [{$row->provider_job_id}], not [{$snapshot->id}].");
            }

            // Correlation fields can arrive on any snapshot, including a stale one.
            $row->provider_job_id ??= $snapshot->id;
            $row->rates ??= $snapshot->rates;

            // rank is monotonic, so a duplicate or stale snapshot changes nothing else.
            if ($snapshot->status->rank() <= $row->status->rank()) {
                return;
            }

            match ($snapshot->status) {
                GenerationStatus::Processing => $this->process($row, $snapshot),
                GenerationStatus::Succeeded => $this->succeed($row, $snapshot),
                GenerationStatus::Failed => $this->fail(
                    $row,
                    $snapshot->errorCode ?? 'unknown',
                    $snapshot->errorMessage ?? 'Pixelforge did not say why.',
                    $snapshot->startedAt,
                    $snapshot->completedAt ?? CarbonImmutable::now(),
                ),
                GenerationStatus::Queued => null,
            };
        });
    }

    /**
     * Fails a generation that Pixelforge never accepted, so there is nothing on
     * its side to bill. Once it has a provider id only Pixelforge decides its outcome.
     */
    public function failBeforeAcceptance(Generation $generation, string $code, string $message): Generation
    {
        return $this->locked($generation, function (Generation $row) use ($code, $message): void {
            if ($row->status !== GenerationStatus::Queued || $row->provider_job_id !== null) {
                Log::warning('pixelforge.generation.local_failure_ignored', ['generation_id' => $row->id, 'code' => $code]);

                return;
            }

            $this->fail($row, $code, $message, null, CarbonImmutable::now());
        });
    }

    /** @param Closure(Generation): void $apply */
    private function locked(Generation $generation, Closure $apply): Generation
    {
        return DB::transaction(function () use ($generation, $apply): Generation {
            // Serializes concurrent writers; whoever waits re-reads the new rank and no-ops.
            $row = Generation::query()->whereKey($generation->getKey())->lockForUpdate()->firstOrFail();

            $apply($row);

            if ($row->isDirty()) {
                $row->save();
            }

            return $row;
        });
    }

    private function process(Generation $row, PixelforgeGeneration $snapshot): void
    {
        $row->status = GenerationStatus::Processing;
        $row->started_at = $snapshot->startedAt ?? CarbonImmutable::now();
    }

    private function succeed(Generation $row, PixelforgeGeneration $snapshot): void
    {
        if ($snapshot->usage === null || $snapshot->rates === null || $snapshot->completedAt === null) {
            throw InvalidPixelforgePayload::at('$', 'a succeeded generation with usage, rates and completed_at');
        }

        // Billed at the rates Pixelforge fixed on this generation, never current pricing.
        $cost = $snapshot->rates->cost($snapshot->usage);
        $charge = $cost->withMarkup($this->markupBasisPoints);

        $row->status = GenerationStatus::Succeeded;
        $row->rates = $snapshot->rates;
        $row->usage_input_text_tokens = $snapshot->usage->inputText;
        $row->usage_input_image_tokens = $snapshot->usage->inputImage;
        $row->usage_output_tokens = $snapshot->usage->output;
        $row->usage_reasoning_tokens = $snapshot->usage->reasoning;
        $row->provider_cost_nano = $cost;
        $row->charge_nano = $charge;
        $row->image_url = $snapshot->imageUrl;
        $row->image_url_expires_at = $snapshot->imageUrlExpiresAt;
        $row->started_at ??= $snapshot->startedAt;
        $row->completed_at = $snapshot->completedAt;

        if ($charge->isGreaterThan($row->estimated_max_charge_nano)) {
            Log::warning('pixelforge.pricing.above_estimate', [
                'generation_id' => $row->id,
                'charge_nano' => $charge->nano,
                'estimated_max_charge_nano' => $row->estimated_max_charge_nano->nano,
            ]);
        }
    }

    // Failed generations are never billed by Pixelforge, so we never charge for them.
    private function fail(Generation $row, string $code, string $message, ?CarbonImmutable $startedAt, CarbonImmutable $completedAt): void
    {
        $row->status = GenerationStatus::Failed;
        $row->provider_cost_nano = Money::zero();
        $row->charge_nano = Money::zero();
        $row->error_code = $code;
        $row->error_message = $message;
        $row->started_at ??= $startedAt;
        $row->completed_at = $completedAt;
    }
}
