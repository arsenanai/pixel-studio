<?php

declare(strict_types=1);

namespace App\Services\Pixelforge;

use App\Data\Pixelforge\GenerationRequest;
use App\Data\Pixelforge\PixelforgeGeneration;
use App\Data\Pixelforge\PixelforgeUsage;
use Carbon\CarbonImmutable;

interface PixelforgeClient
{
    public function createGeneration(GenerationRequest $request): PixelforgeGeneration;

    public function generation(string $id): PixelforgeGeneration;

    public function usage(CarbonImmutable $from, CarbonImmutable $to): PixelforgeUsage;

    public function pricing(): PixelforgePricing;
}
