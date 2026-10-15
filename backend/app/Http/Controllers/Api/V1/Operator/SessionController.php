<?php

namespace App\Http\Controllers\Api\V1\Operator;

use App\Actions\Project\ConfirmStageDeadline;
use App\Actions\Project\SubmitTrackResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\ConfirmStageDeadlineRequest;
use App\Http\Requests\Operator\SubmitTrackResultRequest;
use App\Http\Resources\OperatorProjectStageResource;
use App\Http\Resources\RecordingSessionListResource;
use App\Http\Resources\RecordingSessionResource;
use App\Http\Resources\TrackStageSubmissionResource;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\RecordingSession;
use App\Models\TrackStageProgress;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SessionController extends Controller
{
    public function confirmDeadline(ConfirmStageDeadlineRequest $request, int $stage, ConfirmStageDeadline $action): OperatorProjectStageResource
    {
        $projectStage = ProjectStage::query()->findOrFail($stage);
        $deadline = CarbonImmutable::parse($request->validated('deadline_at'), config('app.timezone'));

        return new OperatorProjectStageResource($action->handle($projectStage, $request->user(), $deadline));
    }

    public function submitTrackResult(SubmitTrackResultRequest $request, int $progress, ProjectTrack $track, SubmitTrackResult $action): JsonResponse
    {
        $trackStageProgress = TrackStageProgress::query()->findOrFail($progress);
        $this->authorize('submit', $trackStageProgress);

        return (new TrackStageSubmissionResource($action->handle(
            $trackStageProgress,
            $track,
            $request->user(),
            $request->validated('result_url'),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * List sessions assigned to the authenticated operator.
     */
    public function index(): AnonymousResourceCollection
    {
        $operator = request()->user();

        $sessions = RecordingSession::with(['room', 'client'])
            ->whereHas('stage', fn ($query) => $query->where('operator_id', $operator->id))
            ->latest('start_at')
            ->paginate(15);

        return RecordingSessionListResource::collection($sessions);
    }

    /**
     * View a single session detail (must be assigned to operator).
     */
    public function show(int $id): JsonResponse
    {
        $session = RecordingSession::with(['room', 'client'])
            ->findOrFail($id);

        $this->authorize('view', $session);

        return (new RecordingSessionResource($session))->response();
    }
}
