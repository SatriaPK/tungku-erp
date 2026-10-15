<?php

namespace App\Http\Resources;

use App\Models\TrackStageProgress;
use App\Models\TrackStageSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TrackStageSubmission */
class FinalDeliveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'track_id' => $this->getProgress()->project_track_id,
            'track_title' => $this->getProgress()->track->title,
            'stage' => $this->getProgress()->stage->stage->value,
            'version' => $this->version,
            'result_url' => $this->result_url,
            'submitted_at' => $this->getAttribute('submitted_at')?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }

    private function getProgress(): TrackStageProgress
    {
        /** @var TrackStageProgress $progress */
        $progress = $this->progress;

        return $progress;
    }
}
