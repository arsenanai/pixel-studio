<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\Pixelforge\HttpPixelforgeClient;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgePricing;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // Lazy loading, unknown attributes and unfillable writes fail loudly outside production.
        Model::shouldBeStrict(! $this->app->isProduction());

        Gate::define('view-spend-report', fn (User $user): bool => $user->is_admin);

        RateLimiter::for('quotes', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('generations', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
