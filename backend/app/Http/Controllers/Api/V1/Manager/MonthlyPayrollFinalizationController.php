<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Finance\FinalizeMonthlyPayroll;
use App\Actions\Finance\GetPayrollRecommendations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\FinalizeMonthlyPayrollRequest;
use App\Http\Resources\Manager\PayrollAllocationResource;

class MonthlyPayrollFinalizationController extends Controller
{
    public function store(FinalizeMonthlyPayrollRequest $request, FinalizeMonthlyPayroll $action, GetPayrollRecommendations $recommendations): PayrollAllocationResource
    {
        $year = (int) $request->validated('year');
        $month = (int) $request->validated('month');
        $action->handle($year, $month, $request->user());

        return new PayrollAllocationResource($recommendations->handle($year, $month));
    }
}
