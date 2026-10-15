<?php

namespace App\Http\Resources\Manager;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollAllocationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'period' => $this['period'],
            'status' => $this['status'],
            'studio_profit_percentage' => $this['studio_profit_percentage'],
            'studio_profit_amount' => $this['studio_profit_amount'],
            'allocations' => $this['allocations'],
        ];
    }
}
