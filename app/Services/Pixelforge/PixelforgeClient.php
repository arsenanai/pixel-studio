<?php

declare(strict_types=1);

namespace App\Services\Pixelforge;

use App\Data\Pixelforge\GenerationRequest;
use App\Data\Pixelforge\PixelforgeGeneration;
use App\Data\Pixelforge\PixelforgeUsage;
use App\Exceptions\InvalidPixelforgePayload;
use App\Exceptions\PixelforgeRequestFailed;
use App\Exceptions\PixelforgeUnreachable;
use Carbon\CarbonImmutable;

/**
 * @throws PixelforgeRequestFailed
 * @throws PixelforgeUnreachable
 * @throws InvalidPixelforgePayload
 */
interface PixelforgeClient
{
    public function createGeneration(GenerationRequest $request): PixelforgeGeneration;

    public function generation(string $id): PixelforgeGeneration;

    /** Inclusive UTC dates, at most 92 days apart. */
    public function usage(CarbonImmutable $from, CarbonImmutable $to): PixelforgeUsage;

    public function pricing(): PixelforgePricing;
}
