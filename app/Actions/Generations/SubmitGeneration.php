<?php

declare(strict_types=1);

namespace App\Actions\Generations;

use App\Enums\GenerationStatus;
use App\Enums\PixelforgeErrorCode;
use App\Exceptions\PixelforgeRequestFailed;
use App\Exceptions\PixelforgeUnreachable;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Support\Facades\Log;

final readonly class SubmitGeneration
{
    public function __construct(
        private PixelforgeClient $client,
        private ApplyGenerationUpdate $apply,
    ) {}

    /**
     * @throws PixelforgeRequestFailed
     * @throws PixelforgeUnreachable
     */
    public function __invoke(Generation $generation): void
    {
        if ($generation->status !== GenerationStatus::Queued || $generation->provider_job_id !== null) {
            return;
        }

        $generation->increment('submit_attempts', 1, ['submitted_at' => $generation->submitted_at ?? now()]);

        try {
            $snapshot = $this->client->createGeneration($generation->toPixelforgeRequest());
        } catch (PixelforgeRequestFailed $failure) {
            if ($failure->isRetryable()) {
                throw $failure;
            }

            Log::log($this->isOurFault($failure) ? 'critical' : 'info', 'pixelforge.create.rejected', [
                'generation_id' => $generation->id,
                'status' => $failure->status,
                'code' => $failure->errorCode?->value,
                'message' => $failure->getMessage(),
            ]);

            $this->apply->failBeforeAcceptance(
                $generation,
                $failure->errorCode->value ?? "http_{$failure->status}",
                $this->explain($failure),
            );

            return;
        }

        ($this->apply)($generation, $snapshot);
    }

    private function explain(PixelforgeRequestFailed $failure): string
    {
        return match ($failure->errorCode) {
            PixelforgeErrorCode::ContentPolicyViolation => 'Pixelforge declined this prompt under its content policy.',
            PixelforgeErrorCode::DailyLimitExceeded => 'The daily Pixelforge generation limit has been reached. It resets at midnight UTC.',
            PixelforgeErrorCode::ValidationFailed => "Pixelforge rejected the request: {$failure->getMessage()}",
            default => 'Pixelforge could not accept this generation.',
        };
    }

    private function isOurFault(PixelforgeRequestFailed $failure): bool
    {
        return in_array($failure->errorCode, [
            PixelforgeErrorCode::InvalidApiKey,
            PixelforgeErrorCode::ValidationFailed,
            PixelforgeErrorCode::IdempotencyKeyReused,
        ], true) || $failure->errorCode === null;
    }
}
