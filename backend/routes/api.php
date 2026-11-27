<?php

use App\Http\Controllers\Api\V1\Client\PackageCatalogController;
use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'active']);

Route::prefix('v1')->group(function () {
    require __DIR__.'/Api/auth.php';
    require __DIR__.'/Api/manager.php';
    require __DIR__.'/Api/client.php';
    require __DIR__.'/Api/operator.php';

    // Katalog paket untuk halaman pengenalan studio sebelum login (hanya baca).
    Route::get('public/packages', [PackageCatalogController::class, 'index'])->middleware('throttle:60,1');

    Route::middleware(['auth:sanctum', 'active'])->prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->middleware('throttle:inbox-read');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->middleware('throttle:inbox-read');
        Route::patch('read-all', [NotificationController::class, 'readAll'])->middleware('throttle:inbox-write');
        Route::patch('{notification}/read', [NotificationController::class, 'read'])->middleware('throttle:inbox-write')->whereUuid('notification');
    });

    require __DIR__.'/Api/webhooks.php';
});
