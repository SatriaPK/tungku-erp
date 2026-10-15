<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Models\RecordingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecordingSession
 */
class RecordingSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room' => new RoomResource($this->whenLoaded('room')),
            'client' => new UserResource($this->whenLoaded('client')),
            'package_id' => $this->package_id,
            'quotation_id' => $this->whenLoaded('project', function () {
                /** @var Project|null $project */
                $project = $this->project;

                return $project?->quotation_id;
            }),
            'start_at' => $this->start_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'end_at' => $this->end_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'held_until' => $this->held_until?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'project_id' => $this->project_id,
            'project_stage_id' => $this->project_stage_id,
            'created_at' => $this->created_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'updated_at' => $this->updated_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
