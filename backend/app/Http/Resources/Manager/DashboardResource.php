<?php

namespace App\Http\Resources\Manager;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'as_of' => $this['as_of'],
            'period' => $this['period'],
            'revenue' => $this['revenue'],
            'expense' => $this['expense'],
            'monthly' => $this['monthly'],
            'annual_totals' => $this['annual_totals'],
            'awaiting_final_payment' => $this['awaiting_final_payment'],
            'in_progress_projects' => $this['in_progress_projects'],
            'tracks_by_stage' => $this['tracks_by_stage'],
            'unclassified_track_count' => $this['unclassified_track_count'],
            'quotations' => $this['quotations'],
            'package_sales' => $this['package_sales'],
            'expense_categories' => $this['expense_categories'],
            'recording_schedule' => $this['recording_schedule'],
            'project_progress' => $this['project_progress'],
            'quotation_progress' => $this['quotation_progress'],
            'operator_activities' => $this['operator_activities'],
        ];
    }
}
