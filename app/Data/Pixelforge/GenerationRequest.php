<?php

declare(strict_types=1);

namespace App\Data\Pixelforge;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

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
            'metadata' => $this->metadata === [] ? (object) [] : $this->metadata,
        ];
    }
}
