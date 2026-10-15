<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for availability check responses.
 */
class AvailabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'available' => $this->resource['available'],
            'reason' => $this->resource['reason'] ?? null,
            'requested_start' => $this->resource['requested_start'],
            'requested_end' => $this->resource['requested_end'],
            'room_id' => $this->resource['room_id'],
        ];
    }
}
