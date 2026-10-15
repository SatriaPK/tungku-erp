<?php

namespace App\Http\Resources\Manager;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonthlyFinanceSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'period' => $this['period'],
            'totals' => $this['totals'],
            'expenses_by_category' => $this['expenses_by_category'],
            'status' => $this['status'],
        ];
    }
}
