<?php

declare(strict_types=1);

namespace App\Actions\Generations;

use App\Data\Pixelforge\PixelforgeEvent;
use App\Data\Pixelforge\PixelforgeGeneration;
use App\Models\Generation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class HandlePixelforgeEvent
{
    public function __construct(private ApplyGenerationUpdate $apply) {}

    /** Returns false when this event id was already handled. */
    public function __invoke(PixelforgeEvent $event, string $rawBody, ?int $deliveryAttempt): bool
    {
        $generation = $event->generation === null ? null : $this->find($event->generation);

        if ($event->generation !== null && $generation === null) {
            Log::warning('pixelforge.webhook.unknown_generation', [
                'event_id' => $event->id,
                'provider_job_id' => $event->generation->id,
            ]);
        }

        // The event row and the transition commit together. If applying fails, the
        // event is not recorded either, and Pixelforge's retry gets a clean second try.
        return DB::transaction(function () use ($event, $rawBody, $deliveryAttempt, $generation): bool {
            $inserted = DB::table('webhook_events')->insertOrIgnore([
                'provider_event_id' => $event->id,
                'type' => $event->type,
                'generation_id' => $generation?->id,
                'delivery_attempt' => $deliveryAttempt,
                'payload' => $rawBody,
                'received_at' => CarbonImmutable::now(),
            ]);

            if ($inserted === 0) {
                Log::info('pixelforge.webhook.duplicate', ['event_id' => $event->id, 'delivery_attempt' => $deliveryAttempt]);

                return false;
            }

            if ($generation !== null && $event->generation !== null) {
                ($this->apply)($generation, $event->generation);
            }

            return true;
        });
    }

    // By our own id first: a webhook can arrive before the create call has returned
    // the provider id, but the metadata we sent is on it from the start.
    private function find(PixelforgeGeneration $snapshot): ?Generation
    {
        $ours = $snapshot->metadataValue(Generation::METADATA_KEY);

        if ($ours !== null && Str::isUuid($ours)) {
            $generation = Generation::query()->find($ours);

            if ($generation !== null) {
                return $generation;
            }
        }

        return Generation::query()->where('provider_job_id', $snapshot->id)->first();
    }
}
