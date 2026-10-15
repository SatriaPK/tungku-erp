<?php

namespace App\Http\Resources\Manager;

use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentListResource extends JsonResource
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

            // Client info
            'client' => $this->when($this->relationLoaded('project') && $this->project?->relationLoaded('client'), function () {
                /** @var User $client */
                $client = $this->project->client;

                return [
                    'id' => $client->id,
                    'name' => $client->name,
                ];
            }),

            // Project info
            'project' => $this->whenLoaded('project', function () {
                /** @var Project $project */
                $project = $this->project;

                return [
                    'id' => $project->id,
                    'code' => $project->code,
                    'name' => $project->name,
                ];
            }),

            'paid_at' => $this->paid_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'created_at' => $this->created_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
