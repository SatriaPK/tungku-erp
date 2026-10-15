<?php

use App\Http\Controllers\Api\V1\ResendWebhookController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/payment/{gateway}', [WebhookController::class, 'handlePaymentWebhook']);
Route::post('webhooks/resend', ResendWebhookController::class);
