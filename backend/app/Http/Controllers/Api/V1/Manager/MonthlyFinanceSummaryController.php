<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Finance\GetMonthlySummary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\MonthlyFinanceSummaryRequest;
use App\Http\Resources\Manager\MonthlyFinanceSummaryResource;

class MonthlyFinanceSummaryController extends Controller
{
    public function show(MonthlyFinanceSummaryRequest $request, GetMonthlySummary $action): MonthlyFinanceSummaryResource
    {
        $summary = $action->handle(
            (int) $request->validated('year'),
            (int) $request->validated('month'),
        );

        return new MonthlyFinanceSummaryResource($summary);
    }
}
