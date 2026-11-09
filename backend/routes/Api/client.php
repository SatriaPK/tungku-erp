<?php

use App\Http\Controllers\Api\V1\Client\BookingController;
use App\Http\Controllers\Api\V1\Client\ClientWorkspaceController;
use App\Http\Controllers\Api\V1\Client\FinalDeliveryController;
use App\Http\Controllers\Api\V1\Client\InvoiceController;
use App\Http\Controllers\Api\V1\Client\PackageCatalogController;
use App\Http\Controllers\Api\V1\Client\PackageRecommendationController;
use App\Http\Controllers\Api\V1\Client\PaymentController;
use App\Http\Controllers\Api\V1\Client\QuotationController;
use Illuminate\Support\Facades\Route;

Route::prefix('client')
    ->middleware(['auth:sanctum', 'active', 'role:client'])
    ->group(function () {
        Route::get('package-recommendations', PackageRecommendationController::class);
        Route::get('packages', [PackageCatalogController::class, 'index']);
        Route::get('base-prices', [PackageCatalogController::class, 'prices']);
        Route::get('rooms', [ClientWorkspaceController::class, 'rooms']);
        Route::get('projects', [ClientWorkspaceController::class, 'projects']);
        Route::get('projects/{project}/final-delivery', [FinalDeliveryController::class, 'show'])
            ->whereNumber('project');

        Route::prefix('bookings')->group(function () {
            Route::get('/', [BookingController::class, 'index']);
            Route::get('{id}', [BookingController::class, 'show'])
                ->whereNumber('id');
            Route::post('check-availability', [BookingController::class, 'checkAvailability']);
            Route::post('hold', [BookingController::class, 'holdSlot']);
            Route::post('confirm', [BookingController::class, 'confirmSlot']);
            Route::delete('{id}', [BookingController::class, 'cancelBooking'])
                ->whereNumber('id');
        });

        Route::prefix('quotations')->group(function () {
            Route::get('/', [QuotationController::class, 'index']);
            Route::get('{quotation}', [QuotationController::class, 'show']);
            Route::post('/', [QuotationController::class, 'store']);
            Route::post('{quotation}/respond', [QuotationController::class, 'respond']);
        });

        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::get('{invoice}', [InvoiceController::class, 'show']);
            Route::post('{invoice}/initiate-payment', [PaymentController::class, 'initiatePayment']);
        });

        Route::prefix('payments')->group(function () {
            Route::get('{payment}', [PaymentController::class, 'show']);
            Route::post('{payment}/final/initiate', [PaymentController::class, 'initiateFinalPayment']);
        });
    });
