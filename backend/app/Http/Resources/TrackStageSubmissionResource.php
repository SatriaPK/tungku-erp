<?php

namespace App\Http\Resources;

use App\Models\TrackStageSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrackStageSubmission
 */
class TrackStageSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'track_stage_progress_id' => $this->track_stage_progress_id,
            'version' => $this->version,
            'result_url' => $this->result_url,
            'submitted_by' => UserResource::make($this->whenLoaded('submittedBy')),
            'submitted_at' => $this->getAttribute('submitted_at')?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->getAttribute('reviewed_at')?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'review_decision' => $this->review_decision,
            'reviewed_by' => UserResource::make($this->whenLoaded('reviewedBy')),
        ];
    }
}
