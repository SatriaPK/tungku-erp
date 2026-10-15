<?php

namespace App\Http\Controllers\Api\V1\Operator;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrackStageSubmissionResource;
use App\Models\TrackStageProgress;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TrackStageSubmissionHistoryController extends Controller
{
    public function index(TrackStageProgress $progress): AnonymousResourceCollection
    {
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
