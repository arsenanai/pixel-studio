<?php

declare(strict_types=1);

namespace App\Data\Pixelforge;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Exceptions\InvalidPixelforgePayload;
use App\ValueObjects\TokenRates;
use App\ValueObjects\TokenUsage;
use Carbon\CarbonImmutable;

/** A generation object as Pixelforge returns it from the API and in webhooks. */
final readonly class PixelforgeGeneration
{
    /**
     * @param  list<string>  $referenceImages
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        public string $prompt,
        public ImageSize $size,
        public ImageQuality $quality,
        public array $referenceImages,
        public array $metadata,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public ?TokenUsage $usage,
        public ?TokenRates $rates,
        public ?string $errorCode,
        public ?string $errorMessage,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        $error = $payload->nullableObject('error');

        $generation = new self(
            id: $payload->string('id'),
            status: $payload->enum('status', GenerationStatus::class),
            prompt: $payload->string('prompt'),
            size: $payload->enum('size', ImageSize::class),
            quality: $payload->enum('quality', ImageQuality::class),
            referenceImages: $payload->stringList('reference_images'),
            metadata: $payload->stringMap('metadata'),
            imageUrl: $payload->nullableString('image_url'),
            imageUrlExpiresAt: $payload->nullableTimestamp('image_url_expires_at'),
            usage: $payload->nullableObject('usage')?->tokenUsage(),
            rates: $payload->has('rates') ? $payload->tokenRates('rates') : null,
            errorCode: $error?->string('code'),
            errorMessage: $error?->string('message'),
            createdAt: $payload->timestamp('created_at'),
            startedAt: $payload->nullableTimestamp('started_at'),
            completedAt: $payload->nullableTimestamp('completed_at'),
        );

        // A finished generation we cannot price or explain is worse than no update:
        // reject it here, and let a later webhook or poll bring a complete one.
        if ($generation->status === GenerationStatus::Succeeded
            && ($generation->usage === null || $generation->rates === null || $generation->completedAt === null)) {
            throw InvalidPixelforgePayload::at('$', 'a succeeded generation with usage, rates and completed_at');
        }

        if ($generation->status === GenerationStatus::Failed && $generation->errorCode === null) {
            throw InvalidPixelforgePayload::at('$.error', 'present on a failed generation');
        }

        return $generation;
    }

    public function metadataValue(string $key): ?string
    {
        return $this->metadata[$key] ?? null;
    }
}
