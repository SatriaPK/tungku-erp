<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\RecordingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
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
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
            'notes' => $this->notes,
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'paid_at' => $this->paid_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'created_at' => $this->created_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),

            // Relationships
            'client' => $this->whenLoaded('client', function () {
                /** @var User $client */
                $client = $this->client;

                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                ];
            }),
            'project' => $this->whenLoaded('project', function () {
                /** @var Project|null $project */
                $project = $this->project;

                return $project ? [
                    'id' => $project->id,
                    'name' => $project->name,
                ] : null;
            }),
            'recording_session' => $this->whenLoaded('recordingSession', function () {
                /** @var RecordingSession|null $recordingSession */
                $recordingSession = $this->recordingSession;

                return $recordingSession ? [
                    'id' => $recordingSession->id,
                ] : null;
            }),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payment' => $this->whenLoaded('latestPayment', function () {
                return $this->latestPayment ? new PaymentResource($this->latestPayment) : null;
            }),
        ];
    }
}
