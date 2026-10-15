<?php

namespace App\Http\Resources;

use App\Models\RecordingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecordingSession
 */
class RecordingExtensionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room' => new RoomResource($this->whenLoaded('room')),
            'start_at' => $this->start_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'end_at' => $this->end_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'status' => $this->status->value,
            'is_extension' => $this->is_extension,
            'extra_fee' => $this->extra_fee,
            'project_stage_id' => $this->project_stage_id,
            'created_at' => $this->created_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
