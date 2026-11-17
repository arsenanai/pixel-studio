<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Generations\SubmitGeneration;
use App\Exceptions\PixelforgeRequestFailed;
use App\Exceptions\PixelforgeUnreachable;
use App\Models\Generation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SubmitGenerationJob implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Queueable;

    private const MAX_DELAY_SECONDS = 60;

    /** @var list<int> */
    public array $backoff = [5, 15, 60];

    public int $uniqueFor = 900;

    public function __construct(public Generation $generation) {}

    public function uniqueId(): string
    {
        return $this->generation->id;
    }

    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::now()->addMinutes(15);
    }

    public function handle(SubmitGeneration $submit): void
    {
        try {
            $submit($this->generation);
        } catch (PixelforgeRequestFailed $failure) {
            $this->release($this->delay($failure->retryAfterSeconds));
        } catch (PixelforgeUnreachable) {
            $this->release($this->delay(null));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('pixelforge.submit.gave_up', [
            'generation_id' => $this->generation->id,
            'exception' => $exception?->getMessage(),
        ]);
    }

    private function delay(?int $retryAfter): int
    {
        $exponential = min(self::MAX_DELAY_SECONDS, 5 * 2 ** max(0, $this->attempts() - 1));

        return max($retryAfter ?? 0, $exponential);
    }
}
