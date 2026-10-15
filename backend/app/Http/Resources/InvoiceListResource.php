<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total' => $this->total,
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'created_at' => $this->created_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),

            // Minimal relations
            'project' => $this->whenLoaded('project', function () {
                /** @var Project|null $project */
                $project = $this->project;

                return $project ? [
                    'id' => $project->id,
                    'name' => $project->name,
                ] : null;
            }),
        ];
    }
}
