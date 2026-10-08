<?php

use App\Http\Controllers\Api\V1\Manager\BasePriceController;
use App\Http\Controllers\Api\V1\Manager\PackageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active', 'role:manager'])
    ->prefix('manager')
    ->group(function () {
        Route::get('base-prices', [BasePriceController::class, 'index']);
        Route::put('base-prices', [BasePriceController::class, 'update']);

        // Harus didaftarkan sebelum apiResource supaya tidak dibaca sebagai {package}
        Route::post('packages/calculate-price', [PackageController::class, 'calculatePrice']);
        Route::apiResource('packages', PackageController::class);
    });