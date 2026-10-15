<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        // Logout tidak diberi middleware active, supaya akun nonaktif tetap bisa keluar
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me'])->middleware('active');
        Route::delete('crm-consent', [AuthController::class, 'revokeCrmConsent'])->middleware('active')->name('auth.crm-consent.destroy');
    });
});
