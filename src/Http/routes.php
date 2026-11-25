<?php

use Illuminate\Support\Facades\Route;
use MirkoHaaser\Teams\Http\Controllers\BotController;
use MirkoHaaser\Teams\Http\Controllers\SsoController;

Route::prefix((string) config('teams.routes.prefix', 'api/teams'))
    ->middleware((array) config('teams.routes.middleware', ['api']))
    ->name('teams.')
    ->group(function (): void {
        Route::post('bot/messages', BotController::class)->name('bot.messages');
        Route::post('auth/sso', SsoController::class)->middleware('throttle:teams-sso')->name('auth.sso');
    });
