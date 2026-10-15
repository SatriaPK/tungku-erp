<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Project\ReviewTrackResult;
use App\Enum\TrackResultReviewDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\ReviewTrackResultRequest;
use App\Http\Resources\TrackStageReviewResource;
use App\Models\TrackStageProgress;
use App\Models\TrackStageSubmission;

class TrackStageReviewController extends Controller
{
    public function store(ReviewTrackResultRequest $request, int $progress, TrackStageSubmission $submission, ReviewTrackResult $action): TrackStageReviewResource
    {
        $trackStageProgress = TrackStageProgress::query()->findOrFail($progress);
        $this->authorize('review', $trackStageProgress);

        $reviewedProgress = $action->handle(
            $trackStageProgress,
            $submission,
            $request->user(),
            TrackResultReviewDecision::from($request->validated('decision')),
            $request->validated('review_notes'),
        );

        return new TrackStageReviewResource($reviewedProgress);
    }
}
