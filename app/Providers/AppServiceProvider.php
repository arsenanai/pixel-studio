<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Pixelforge\HttpPixelforgeClient;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgePricing;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PixelforgeClient::class, HttpPixelforgeClient::class);

        $this->app->bind(
            PixelforgePricing::class,
            fn (Application $app): PixelforgePricing => $app->make(PixelforgeClient::class)->pricing(),
        );
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
