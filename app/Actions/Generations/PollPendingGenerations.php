<?php

declare(strict_types=1);

namespace App\Actions\Generations;

use App\Enums\PixelforgeErrorCode;
use App\Exceptions\InvalidPixelforgePayload;
use App\Exceptions\PixelforgeRequestFailed;
use App\Exceptions\PixelforgeUnreachable;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

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
                $snapshot = $this->client->generation((string) $generation->provider_job_id);
            } catch (PixelforgeRequestFailed $failure) {
                if ($failure->isRetryable()) {
                    break;
                }

                Log::error('pixelforge.poll.failed', [
                    'generation_id' => $generation->id,
                    'status' => $failure->status,
                    'code' => $failure->errorCode?->value,
                    'not_found' => $failure->is(PixelforgeErrorCode::NotFound),
                ]);
                $generation->touch();

                continue;
            } catch (PixelforgeUnreachable) {
                break;
            } catch (InvalidPixelforgePayload $invalid) {
                Log::warning('pixelforge.poll.invalid_payload', ['generation_id' => $generation->id, 'reason' => $invalid->getMessage()]);
                $generation->touch();

                continue;
            }

            $applied = ($this->apply)($generation, $snapshot);

            if ($applied->updated_at->equalTo($generation->updated_at)) {
                $applied->touch();
            }

            $polled++;
        }

        return $polled;
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
