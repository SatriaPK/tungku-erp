<?php

namespace App\Http\Resources\Manager;

use App\Models\Project;
use App\Models\ProjectStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectIndexResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ProjectStage|null $stage */
        $stage = $this->currentStage;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'client' => $this->whenLoaded('client', fn (): array => [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'avatar_url' => null,
            ]),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'started_at' => $stage?->started_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'created_at' => $this->created_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'current_stage' => $stage === null ? null : [
                'stage' => $stage->stage->value,
                'stage_label' => $stage->stage->label(),
                'status' => $stage->status->value,
                'status_label' => $stage->status->label(),
                'operator' => $stage->relationLoaded('operator') && $stage->operator !== null ? [
                    'id' => $stage->operator->id,
                    'name' => $stage->operator->name,
                    'avatar_url' => null,
                ] : null,
            ],
            'progress_percent' => $this->progress_percent,
            'tracks_count' => $this->tracks_count,
            'tracks_approved_count' => $this->tracks_approved_count,
        ];
    }
}
