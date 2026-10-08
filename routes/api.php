<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyNetworkSignature;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyNetworkSignature::class)
    ->prefix('network')
    ->group(function (): void {
        //
    });
