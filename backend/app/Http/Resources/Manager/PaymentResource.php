<?php

namespace App\Http\Resources\Manager;

use App\Http\Resources\InvoiceResource;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'amount' => $this->amount,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'provider' => $this->provider->value,
            'provider_label' => $this->provider->label(),
            'payment_method' => $this->payment_method,
            'payment_url' => $this->payment_url,
            'snap_token' => $this->snap_token,
            'gateway_charge_id' => $this->gateway_charge_id,
            'notes' => $this->notes,

            'expires_at' => $this->expires_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'paid_at' => $this->paid_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'created_at' => $this->created_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'updated_at' => $this->updated_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),

            // Relationships
            'client' => $this->when($this->relationLoaded('project') && $this->project?->relationLoaded('client'), function () {
                /** @var User $client */
                $client = $this->project->client;

                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                ];
            }),

            'project' => $this->whenLoaded('project', function () {
                /** @var Project $project */
                $project = $this->project;

                return [
                    'id' => $project->id,
                    'code' => $project->code,
                    'name' => $project->name,
                ];
            }),

            'invoice' => $this->whenLoaded('invoice', function () {
                return new InvoiceResource($this->invoice);
            }),

            // Gateway response (for debugging/audit)
            'gateway_response' => $this->when(
                $request->user()?->isManager() && $this->gateway_response,
                $this->gateway_response
            ),
        ];
    }
}
