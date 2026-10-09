<?php

declare(strict_types=1);

use App\Http\Controllers\Network\AuthorizationController;
use App\Http\Controllers\Network\CardAvailableController;
use App\Http\Controllers\Network\CardStatementController;
use App\Http\Controllers\Network\EventController;
use App\Http\Middleware\VerifyNetworkSignature;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyNetworkSignature::class)
    ->prefix('network')
    ->group(function (): void {
        Route::post('/authorizations', [AuthorizationController::class, 'store']);
        Route::post('/events', [EventController::class, 'store']);
        Route::get('/cards/{cardToken}/available', [CardAvailableController::class, 'show']);
        Route::get('/cards/{cardToken}/statement', [CardStatementController::class, 'show']);
    });
