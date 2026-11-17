<?php

use App\Http\Controllers\GenerateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/generate');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/generate', [GenerateController::class, 'create'])->name('generate');
    Route::post('/generations', [GenerateController::class, 'store'])
        ->middleware('throttle:generations')
        ->name('generations.store');
    Route::post('/quote', QuoteController::class)
        ->middleware('throttle:quotes')
        ->name('quote');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
