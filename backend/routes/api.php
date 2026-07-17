<?php

use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\SessionController;
use App\Http\Controllers\Api\Mobile\ScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('mobile.token')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/sessions', [SessionController::class, 'index']);
        Route::get('/sessions/{session}', [SessionController::class, 'show']);
        Route::post('/sessions/{session}/scan', [ScanController::class, 'store']);
    });
});
