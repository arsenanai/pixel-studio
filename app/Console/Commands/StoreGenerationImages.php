<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Jobs\StoreGenerationImage;
use App\Models\Generation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class StoreGenerationImages extends Command
{
    protected $signature = 'pixelforge:store-images';

    protected $description = 'Queue a copy of every finished image whose Pixelforge link has not expired yet';

    public function handle(): int
    {
        $queued = 0;

        Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->whereNull('image_path')
            ->whereNotNull('image_url')
            ->where('image_url_expires_at', '>', CarbonImmutable::now())
            ->select('id')
            ->lazyById()
            ->each(function (Generation $generation) use (&$queued): void {
                StoreGenerationImage::dispatch($generation->id);
                $queued++;
            });

        $this->info("Queued {$queued} images to copy.");

        return self::SUCCESS;
    }
}
