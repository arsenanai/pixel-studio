<?php

declare(strict_types=1);

namespace App\Data\Pixelforge;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

/**
 * The body of POST /v1/generations. Pixelforge replays an idempotent request
 * only when the parameters are identical, so this is built once per generation
 * and sent unchanged on every retry.
 */
final readonly class GenerationRequest
{
    /**
     * @param  list<string>  $referenceImages
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public string $idempotencyKey,
        public string $prompt,
        public ImageSize $size,
        public ImageQuality $quality,
        public array $referenceImages,
        public array $metadata,
    ) {}

    /** @return array{prompt: string, size: string, quality: string, reference_images: list<string>, metadata: array<string, string>|object} */
    public function body(): array
    {
        return [
            'prompt' => $this->prompt,
            'size' => $this->size->value,
            'quality' => $this->quality->value,
            'reference_images' => $this->referenceImages,
            // An empty PHP array would encode as [], which is not an object.
            'metadata' => $this->metadata === [] ? (object) [] : $this->metadata,
        ];
    }
}
