<?php

use App\Http\Controllers\Api\V1\Manager\BasePriceController;
use App\Http\Controllers\Api\V1\Manager\BookingController;
use App\Http\Controllers\Api\V1\Manager\ClientRecommendationController;
use App\Http\Controllers\Api\V1\Manager\CRMCampaignController;
use App\Http\Controllers\Api\V1\Manager\DashboardController;
use App\Http\Controllers\Api\V1\Manager\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\Manager\ExpenseController;
use App\Http\Controllers\Api\V1\Manager\InventoryCategoryController;
use App\Http\Controllers\Api\V1\Manager\InventoryController;
use App\Http\Controllers\Api\V1\Manager\MonthlyFinanceSummaryController;
use App\Http\Controllers\Api\V1\Manager\MonthlyPayrollFinalizationController;
use App\Http\Controllers\Api\V1\Manager\PackageController;
use App\Http\Controllers\Api\V1\Manager\PaymentController;
use App\Http\Controllers\Api\V1\Manager\PayrollAllocationController;
use App\Http\Controllers\Api\V1\Manager\ProjectAssignmentController;
use App\Http\Controllers\Api\V1\Manager\QuotationController;
use App\Http\Controllers\Api\V1\Manager\StudioSettingController;
use App\Http\Controllers\Api\V1\Manager\TrackStageReviewController;
use App\Http\Controllers\Api\V1\Manager\TrackStageSubmissionHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active', 'role:manager'])
    ->prefix('manager')
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'show']);
        Route::get('dashboard/operator-activities', [DashboardController::class, 'activities']);
        Route::get('base-prices', [BasePriceController::class, 'index']);
        Route::put('base-prices', [BasePriceController::class, 'update']);
        Route::get('crm/recommendations', [ClientRecommendationController::class, 'index']);
        Route::get('crm/campaigns', [CRMCampaignController::class, 'index']);
        Route::post('crm/campaigns', [CRMCampaignController::class, 'store']);
        Route::get('crm/campaigns/{campaign}', [CRMCampaignController::class, 'show'])->whereNumber('campaign');
        Route::post('crm/campaigns/{campaign}/dispatch', [CRMCampaignController::class, 'dispatch'])->whereNumber('campaign');

        Route::apiResource('expense-categories', ExpenseCategoryController::class);
        Route::apiResource('expenses', ExpenseController::class);
        Route::apiResource('inventory-categories', InventoryCategoryController::class)
            ->parameters(['inventory-categories' => 'inventory_category'])
            ->whereNumber('inventory_category');
        Route::apiResource('inventories', InventoryController::class)->whereNumber('inventory');
        Route::post('inventories/{inventory}/dispose', [InventoryController::class, 'dispose'])->whereNumber('inventory');
        Route::get('finance/monthly-summary', [MonthlyFinanceSummaryController::class, 'show']);
        Route::post('finance/payroll-finalization', [MonthlyPayrollFinalizationController::class, 'store']);
        Route::get('finance/payroll-allocations', [PayrollAllocationController::class, 'show']);
        Route::put('finance/payroll-allocations', [PayrollAllocationController::class, 'update']);

        // Harus didaftarkan sebelum apiResource supaya tidak dibaca sebagai {package}
        Route::post('packages/calculate-price', [PackageController::class, 'calculatePrice']);
        Route::apiResource('packages', PackageController::class);

        Route::get('settings', [StudioSettingController::class, 'index']);
        Route::put('settings/{key}', [StudioSettingController::class, 'update']);

        Route::prefix('bookings')->group(function () {
            Route::get('/', [BookingController::class, 'index']);
            Route::get('{id}', [BookingController::class, 'show'])
                ->whereNumber('id');
            Route::delete('{id}', [BookingController::class, 'destroy'])
                ->whereNumber('id');
        });

        // Payment management routes

        Route::prefix('quotations')->group(function () {
            Route::get('/', [QuotationController::class, 'index']);
            Route::get('{quotation}', [QuotationController::class, 'show']);
            Route::post('{quotation}/respond', [QuotationController::class, 'respond']);
        });

        Route::post('projects/{project}/assignments', [ProjectAssignmentController::class, 'store'])
            ->whereNumber('project');

        Route::post('track-progress/{progress}/submissions/{submission}/review', [TrackStageReviewController::class, 'store'])
            ->whereNumber(['progress', 'submission']);
        Route::get('projects/{project}/stages/{stage}/tracks/{track}/history', [TrackStageSubmissionHistoryController::class, 'index'])
            ->whereNumber(['project', 'stage', 'track']);

        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentController::class, 'index']);
            Route::get('{payment}', [PaymentController::class, 'show']);
            Route::post('{payment}/confirm', [PaymentController::class, 'confirm']);
            Route::post('{payment}/refund', [PaymentController::class, 'refund']);
            Route::post('{payment}/cancel', [PaymentController::class, 'cancel']);
        });
    });
