<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class StoreGenerationImage implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Queueable;

    public const MAX_BYTES = 20 * 1024 * 1024;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 120, 600];

    public function __construct(public string $generationId) {}

    public function uniqueId(): string
    {
        return $this->generationId;
    }

    public function handle(#[Config('filesystems.images_disk')] ?string $disk): void
    {
        if ($disk === null) {
            return;
        }

        $generation = Generation::query()->find($this->generationId);

        if ($generation === null
            || $generation->status !== GenerationStatus::Succeeded
            || $generation->image_path !== null
            || $generation->image_url === null
            || $generation->image_url_expires_at?->isPast()) {
            return;
        }

        try {
            $response = Http::timeout(30)->get($generation->image_url);
        } catch (ConnectionException) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 600);

            return;
        }

        $body = $response->body();

        if ($response->failed() || $response->header('Content-Type') !== 'image/png' || strlen($body) > self::MAX_BYTES || ! str_starts_with($body, "\x89PNG")) {
            Log::warning('pixelforge.image.not_stored', [
                'generation_id' => $generation->id,
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'bytes' => strlen($body),
            ]);

            if ($response->serverError()) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 600);
            }

            return;
        }

        $path = "generations/{$generation->id}.png";
        Storage::disk($disk)->put($path, $body);

        Generation::query()->whereKey($generation->id)->whereNull('image_path')->update(['image_path' => $path]);
    }
}
