<?php

use App\Http\Controllers\Api\V1\Operator\RecordingExtensionController;
use App\Http\Controllers\Api\V1\Operator\OperatorWorkspaceController;
use App\Http\Controllers\Api\V1\Operator\SessionController;
use App\Http\Controllers\Api\V1\Operator\TrackStageSubmissionHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active', 'role:operator'])
    ->prefix('operator')
    ->group(function () {
        Route::get('stages', [OperatorWorkspaceController::class, 'stages']);
        Route::get('rooms', [OperatorWorkspaceController::class, 'rooms']);
        Route::post('stages/{stage}/recording-extensions', [RecordingExtensionController::class, 'store'])
            ->whereNumber('stage');
        Route::post('stages/{stage}/confirm-deadline', [SessionController::class, 'confirmDeadline'])
            ->whereNumber('stage');
        Route::post('track-progress/{progress}/tracks/{track}/submissions', [SessionController::class, 'submitTrackResult'])
            ->whereNumber(['progress', 'track']);
        Route::get('track-progress/{progress}/submissions', [TrackStageSubmissionHistoryController::class, 'index'])
            ->whereNumber('progress');

        Route::prefix('sessions')->group(function () {
            Route::get('/', [SessionController::class, 'index']);
            Route::get('{id}', [SessionController::class, 'show'])
                ->whereNumber('id');
        });
    });
