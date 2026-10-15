<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Models\ProjectStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectStage
 */
class ProjectStageAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'stage' => $this->stage->value,
            'sequence' => $this->sequence,
            'operator' => new UserResource($this->whenLoaded('operator')),
            'status' => $this->status->value,
            'progress_percent' => Project::query()->find($this->project_id)?->progressPercent() ?? 0,
            'assigned_at' => $this->assigned_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
