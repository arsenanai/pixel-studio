<?php

use App\Http\Controllers\Webhooks\PixelforgeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/pixelforge/webhook', PixelforgeWebhookController::class)->name('webhooks.pixelforge');
