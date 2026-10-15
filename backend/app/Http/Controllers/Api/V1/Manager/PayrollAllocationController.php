<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Finance\GetPayrollRecommendations;
use App\Actions\Finance\SavePayrollAllocations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\PayrollAllocationRequest;
use App\Http\Resources\Manager\PayrollAllocationResource;

class PayrollAllocationController extends Controller
{
    public function show(PayrollAllocationRequest $request, GetPayrollRecommendations $action): PayrollAllocationResource
    {
        $period = $request->safe()->only(['year', 'month']);

        return new PayrollAllocationResource($action->handle((int) $period['year'], (int) $period['month']));
    }

    public function update(PayrollAllocationRequest $request, SavePayrollAllocations $action, GetPayrollRecommendations $recommendations): PayrollAllocationResource
    {
        $data = $request->validated();
        $year = (int) $data['year'];
        $month = (int) $data['month'];
        $allocations = array_map(static function (array $allocation): array {
            $percentage = (string) $allocation['percentage'];
            [$whole, $fraction] = array_pad(explode('.', $percentage, 2), 2, '');

            return [
                'operator_id' => (int) $allocation['operator_id'],
                'percentage' => sprintf('%d.%02d', (int) $whole, (int) str_pad($fraction, 2, '0')),
            ];
        }, $data['allocations']);

        $studioPercentage = isset($data['studio_profit_percentage'])
            ? sprintf('%d.%02d', (int) explode('.', (string) $data['studio_profit_percentage'])[0], (int) str_pad(explode('.', (string) $data['studio_profit_percentage'])[1] ?? '', 2, '0'))
            : null;

        $action->handle($year, $month, $allocations, $studioPercentage);

        return new PayrollAllocationResource($recommendations->handle($year, $month));
    }
}
