<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrackStageSubmissionResource;
use App\Models\TrackStageProgress;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TrackStageSubmissionHistoryController extends Controller
{
    public function index(int $project, int $stage, int $track): AnonymousResourceCollection|Response
    {
        $progress = TrackStageProgress::query()
            ->where('project_track_id', $track)
            ->whereHas('track', fn ($query) => $query->where('project_id', $project))
            ->whereHas('stage', fn ($query) => $query->where('project_id', $project)->whereKey($stage))
            ->firstOrFail();

        $this->authorize('viewHistory', $progress);

        return TrackStageSubmissionResource::collection(
            $progress->submissions()
                ->with(['submittedBy', 'reviewedBy'])
                ->orderBy('version')
                ->orderBy('id')
                ->paginate(50),
        );
    }
}
