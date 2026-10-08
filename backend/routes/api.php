<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/auth.php';
    require __DIR__ . '/api/manager.php';
 
    // require __DIR__ . '/api/operator.php';
    // require __DIR__ . '/api/client.php';
    // require __DIR__ . '/api/webhooks.php';
});