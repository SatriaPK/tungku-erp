<?php

namespace App\Http\Resources;

use App\Models\ProjectStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectStage
 */
class OperatorProjectStageResource extends JsonResource
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
            'status' => $this->status->value,
            'deadline_at' => $this->deadline_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'started_at' => $this->started_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
