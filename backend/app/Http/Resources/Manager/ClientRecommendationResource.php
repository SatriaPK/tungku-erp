<?php

namespace App\Http\Resources\Manager;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ClientRecommendationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'last_paid_at' => $this->getAttribute('last_paid_at'),
            'has_paid_order_history' => $this->getAttribute('last_paid_at') !== null,
        ];
    }
}
