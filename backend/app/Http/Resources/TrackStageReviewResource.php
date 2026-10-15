<?php

namespace App\Http\Resources;

use App\Models\ProjectStage;
use App\Models\TrackStageProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TrackStageProgress */
class TrackStageReviewResource extends JsonResource
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
            'project_stage_id' => $this->project_stage_id,
            'project_track_id' => $this->project_track_id,
            'status' => $this->getAttribute('status')->value,
            'stage_status' => ProjectStage::query()->find($this->project_stage_id)?->status?->value,
            'result_url' => $this->result_url,
            'current_version' => $this->current_version,
            'review_notes' => $this->review_notes,
            'revision_count' => $this->revision_count,
            'approved_at' => $this->getAttribute('approved_at')?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'approved_by' => $this->approved_by,
            'submission' => TrackStageSubmissionResource::make($this->submissions->first()),
        ];
    }
}
