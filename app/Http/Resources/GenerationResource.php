<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A generation as its owner sees it. Provider cost is ours, not theirs, so it
 * is not included.
 *
 * @property Generation $resource
 */
final class GenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $generation = $this->resource;

        $expired = $generation->image_url_expires_at !== null
            && $generation->image_url_expires_at->lessThanOrEqualTo(CarbonImmutable::now());

        return [
            'id' => $generation->id,
            'prompt' => $generation->prompt,
            'size' => ['value' => $generation->size->value, 'label' => $generation->size->label(), 'dimensions' => $generation->size->dimensions()],
            'quality' => ['value' => $generation->quality->value, 'label' => $generation->quality->label()],
            'referenceImageCount' => count($generation->reference_images),
            'status' => $generation->status->value,
            'statusLabel' => $this->statusLabel($generation),
            'estimate' => [
                'min' => $generation->estimated_min_charge_nano,
                'typical' => $generation->estimated_charge_nano,
                'max' => $generation->estimated_max_charge_nano,
            ],
            'charge' => $generation->charge_nano,
            'image' => $generation->image_url === null ? null : [
                'url' => $expired ? null : $generation->image_url,
                'expiresAt' => $generation->image_url_expires_at?->toIso8601ZuluString(),
            ],
            'error' => $generation->error_message,
            'createdAt' => $generation->created_at->toIso8601ZuluString(),
            'completedAt' => $generation->completed_at?->toIso8601ZuluString(),
        ];
    }

    private function statusLabel(Generation $generation): string
    {
        return match ($generation->status) {
            GenerationStatus::Queued => $generation->provider_job_id === null && $generation->submit_attempts > 1
                ? 'Waiting for Pixelforge'
                : 'Queued',
            GenerationStatus::Processing => 'Generating',
            GenerationStatus::Succeeded => 'Done',
            GenerationStatus::Failed => 'Failed',
        };
    }
}
