<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Finance\GetManagerDashboardMetrics;
use App\Actions\Project\GetManagerDashboardProgress;
use App\Actions\Project\GetOperatorActivities;
use App\Http\Controllers\Controller;
use App\Http\Resources\Manager\DashboardResource;
use App\Http\Resources\Manager\OperatorActivityFeedResource;
use Carbon\CarbonImmutable;

class DashboardController extends Controller
{
    public function show(GetManagerDashboardMetrics $metrics, GetManagerDashboardProgress $progress, GetOperatorActivities $activities): DashboardResource
    {
        $asOf = CarbonImmutable::now(config('app.timezone'));

        return new DashboardResource([
            ...$metrics->handle($asOf),
            ...$progress->handle($asOf),
            'operator_activities' => $activities->handle($asOf),
        ]);
    }

    public function activities(GetOperatorActivities $activities): OperatorActivityFeedResource
    {
        $asOf = CarbonImmutable::now(config('app.timezone'));

        return new OperatorActivityFeedResource([
            'as_of' => $asOf->toIso8601String(),
            'timezone' => config('app.timezone'),
            'items' => $activities->handle($asOf),
        ]);
    }
}
