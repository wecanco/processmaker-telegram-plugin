<?php

use Illuminate\Support\Facades\Route;
use ProcessMaker\TelegramPlugin\Controllers\TelegramAuthController;
use ProcessMaker\TelegramPlugin\Controllers\TelegramBotController;

/*
|--------------------------------------------------------------------------
| Telegram Plugin Web Routes
|--------------------------------------------------------------------------
*/

// User authentication routes (protected)
Route::middleware(['web', 'auth'])->prefix('profile')->name('telegram.')->group(function () {
    Route::get('/telegram', [TelegramAuthController::class, 'show'])->name('show');
    Route::post('/telegram/connect', [TelegramAuthController::class, 'connect'])->name('connect');
    Route::post('/telegram/disconnect', [TelegramAuthController::class, 'disconnect'])->name('disconnect');
    Route::post('/telegram/regenerate-token', [TelegramAuthController::class, 'regenerateToken'])->name('regenerate-token');
});

// Webhook endpoint (public, but will be validated internally)
Route::post('/webhook/telegram', [TelegramBotController::class, 'webhook'])
    ->name('telegram.webhook')
    ->middleware(['api']);

// Health check endpoint for webhook
Route::get('/webhook/telegram/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'service' => 'ProcessMaker Telegram Plugin'
    ]);
})->name('telegram.webhook.health');