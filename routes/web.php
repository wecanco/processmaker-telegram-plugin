<?php

use Illuminate\Support\Facades\Route;
use ProcessMaker\TelegramPlugin\Controllers\TelegramAuthController;

Route::middleware(['auth', 'web'])->group(function () {
    Route::get('/profile/telegram', [TelegramAuthController::class, 'connect'])
        ->name('telegram.connect');
        
    Route::post('/profile/telegram/disconnect', [TelegramAuthController::class, 'disconnect'])
        ->name('telegram.disconnect');
});

Route::post('/telegram/webhook', [TelegramBotController::class, 'webhook'])
    ->name('telegram.webhook');