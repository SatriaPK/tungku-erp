<?php

namespace App\Http\Resources\Operator;

use App\Models\ProjectStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectStage */
class OperatorStageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project_name' => $this->project?->name,
            'client_name' => $this->project?->client?->name,
            'stage' => $this->stage->value,
            'status' => $this->status->value,
            'deadline_at' => $this->deadline_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'tracks' => $this->trackProgress->map(fn ($progress): array => [
                'id' => $progress->track?->id,
                'title' => $progress->track?->title,
                'progress_id' => $progress->id,
                'status' => $progress->status->value,
                'review_notes' => $progress->review_notes,
            ])->values(),
        ];
    }
}
