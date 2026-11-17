<?php

use App\Http\Controllers\Admin\SpendReportController;
use App\Http\Controllers\CreditsController;
use App\Http\Controllers\GenerateController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/generate');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/generate', [GenerateController::class, 'create'])->name('generate');
    Route::post('/generations', [GenerateController::class, 'store'])
        ->middleware('throttle:generations')
        ->name('generations.store');
    Route::get('/history', HistoryController::class)->name('history');
    Route::get('/credits', [CreditsController::class, 'index'])->name('credits');
    Route::post('/credits', [CreditsController::class, 'store'])
        ->middleware('throttle:generations')
        ->name('credits.store');
    Route::post('/quote', QuoteController::class)
        ->middleware('throttle:quotes')
        ->name('quote');
});

Route::middleware(['auth', 'verified', 'can:view-spend-report'])->group(function () {
    Route::get('/admin', SpendReportController::class)->name('admin.report');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
