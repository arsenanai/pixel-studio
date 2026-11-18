<?php

declare(strict_types=1);

namespace App\Actions\Generations;

use App\Enums\PixelforgeErrorCode;
use App\Enums\PollOutcome;
use App\Exceptions\InvalidPixelforgePayload;
use App\Exceptions\PixelforgeRequestFailed;
use App\Exceptions\PixelforgeUnreachable;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class PollPendingGenerations
{
    private const QUIET_SECONDS = 30;

    private const BATCH = 15;

    private const RESUBMIT_AFTER_MINUTES = 2;

    private const GIVE_UP_AFTER_MINUTES = 30;

    public function __construct(
        private PixelforgeClient $client,
        private ApplyGenerationUpdate $apply,
    ) {}

    /** @return array{polled: int, resubmitted: int, abandoned: int} */
    public function __invoke(): array
    {
        $now = CarbonImmutable::now();

        return [
            'polled' => $this->pollAccepted($now),
            ...$this->resubmitUnaccepted($now),
        ];
    }

    private function pollAccepted(CarbonImmutable $now): int
    {
        $generations = Generation::query()
            ->pending()
            ->whereNotNull('provider_job_id')
            ->where('updated_at', '<', $now->subSeconds(self::QUIET_SECONDS))
            ->oldest('updated_at')
            ->limit(self::BATCH)
            ->get();

        $polled = 0;

        foreach ($generations as $generation) {
            try {
                $outcome = $this->pollOne($generation, $now);
            } catch (Throwable $exception) {
                report($exception);
                Log::error('pixelforge.poll.apply_failed', ['generation_id' => $generation->id, 'reason' => $exception->getMessage()]);
                $generation->touch();

                continue;
            }

            if ($outcome === PollOutcome::StopForNow) {
                break;
            }

            $polled += $outcome === PollOutcome::Updated ? 1 : 0;
        }

        return $polled;
    }

    private function pollOne(Generation $generation, CarbonImmutable $now): PollOutcome
    {
        try {
            $snapshot = $this->client->generation((string) $generation->provider_job_id);
        } catch (PixelforgeRequestFailed $failure) {
            if ($failure->isRetryable()) {
                return PollOutcome::StopForNow;
            }

            if ($failure->is(PixelforgeErrorCode::NotFound) && $this->missingForTooLong($generation, $now)) {
                $this->apply->failUnknownToPixelforge($generation);

                return PollOutcome::Updated;
            }

            Log::error('pixelforge.poll.failed', [
                'generation_id' => $generation->id,
                'status' => $failure->status,
                'code' => $failure->errorCode?->value,
            ]);
            $generation->touch();

            return PollOutcome::Skipped;
        } catch (PixelforgeUnreachable) {
            return PollOutcome::StopForNow;
        } catch (InvalidPixelforgePayload $invalid) {
            Log::warning('pixelforge.poll.invalid_payload', ['generation_id' => $generation->id, 'reason' => $invalid->getMessage()]);
            $generation->touch();

            return PollOutcome::Skipped;
        }

        $applied = ($this->apply)($generation, $snapshot);

        if ($applied->updated_at->equalTo($generation->updated_at)) {
            $applied->touch();
        }

        return PollOutcome::Updated;
    }

    private function missingForTooLong(Generation $generation, CarbonImmutable $now): bool
    {
        return ($generation->submitted_at ?? $generation->created_at)->lessThan($now->subMinutes(self::GIVE_UP_AFTER_MINUTES));
    }

    /** @return array{resubmitted: int, abandoned: int} */
    private function resubmitUnaccepted(CarbonImmutable $now): array
    {
        $generations = Generation::query()
            ->pending()
            ->whereNull('provider_job_id')
            ->where('updated_at', '<', $now->subMinutes(self::RESUBMIT_AFTER_MINUTES))
            ->oldest('created_at')
            ->limit(self::BATCH)
            ->get();

        $resubmitted = 0;
        $abandoned = 0;

        foreach ($generations as $generation) {
            if ($generation->created_at->lessThan($now->subMinutes(self::GIVE_UP_AFTER_MINUTES))) {
                $this->apply->failBeforeAcceptance(
                    $generation,
                    'submit_timeout',
                    'Pixelforge was too busy to accept this generation, so it was cancelled.',
                );
                $abandoned++;

                continue;
            }

            SubmitGenerationJob::dispatch($generation);
            $resubmitted++;
        }

        return ['resubmitted' => $resubmitted, 'abandoned' => $abandoned];
    }
}
