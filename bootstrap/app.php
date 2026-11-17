<?php

use App\Exceptions\InvalidPixelforgePayload;
use App\Exceptions\PixelforgeRequestFailed;
use App\Exceptions\PixelforgeUnreachable;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pricing is fetched live for quotes; if Pixelforge is down, say so plainly.
        $exceptions->render(function (PixelforgeUnreachable|PixelforgeRequestFailed|InvalidPixelforgePayload $exception, Request $request) {
            $message = 'Pixelforge pricing is unavailable right now. Try again in a moment; nothing was charged.';

            if ($request->hasHeader('X-Inertia')) {
                return back()->with('error', $message);
            }

            return $request->expectsJson() ? response()->json(['message' => $message], 503) : null;
        });
    })->create();
