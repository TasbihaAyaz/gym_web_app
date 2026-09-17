<?php

use App\Http\Controllers\Api\DesktopAuthController;
use App\Http\Controllers\Api\DesktopCheckinController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/* Desktop check-in client (Tauri) */
Route::prefix('desktop')->group(function () {
    Route::post('login', [DesktopAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [DesktopAuthController::class, 'me']);
        Route::post('logout', [DesktopAuthController::class, 'logout']);
        Route::get('poll-checkin', [DesktopCheckinController::class, 'poll']);
        Route::post('live-sync', [DesktopCheckinController::class, 'liveSync']);
    });
});
