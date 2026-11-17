<?php

use App\Http\Controllers\Webhooks\PixelforgeWebhookController;
use Illuminate\Support\Facades\Route;

// Stateless API group: no session and no CSRF token, which Pixelforge cannot send.
Route::post('/pixelforge/webhook', PixelforgeWebhookController::class)->name('webhooks.pixelforge');
