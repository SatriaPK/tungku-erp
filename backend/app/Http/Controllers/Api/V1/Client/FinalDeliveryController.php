<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Enum\StageType;
use App\Enum\TrackProgressStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FinalDeliveryResource;
use App\Models\Project;
use App\Models\TrackStageSubmission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FinalDeliveryController extends Controller
{
    public function show(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewFinalDelivery', $project);

        $includedStages = $project->includedStageValues();
        $finalStage = collect(StageType::cases())
            ->filter(fn (StageType $stage): bool => in_array($stage->value, $includedStages, true))
            ->last();

        abort_if($finalStage === null, 404);

        $submissions = TrackStageSubmission::query()
            ->whereHas('progress', fn ($query) => $query
                ->where('status', TrackProgressStatus::Approved->value)
                ->whereHas('stage', fn ($stageQuery) => $stageQuery
                    ->where('project_id', $project->id)
                    ->where('stage', $finalStage->value)))
            ->whereHas('progress', fn ($query) => $query
                ->whereColumn('track_stage_progress.current_version', 'track_stage_submissions.version'))
            ->with(['progress.track', 'progress.stage'])
            ->orderBy('id')
            ->get();

        return FinalDeliveryResource::collection($submissions);
    }
}
