<?php

namespace App\Http\Controllers\Api\V1\Operator;

use App\Actions\Booking\RequestRecordingExtension;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\RequestRecordingExtensionRequest;
use App\Http\Resources\RecordingExtensionResource;
use App\Models\ProjectStage;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RecordingExtensionController extends Controller
{
    public function store(RequestRecordingExtensionRequest $request, int $stage, RequestRecordingExtension $action): JsonResponse
    {
        $projectStage = ProjectStage::query()->findOrFail($stage);
        $this->authorize('requestRecordingExtension', $projectStage);

        $recordingSession = $action->handle(
            $projectStage,
            $request->user(),
            (int) $request->validated('room_id'),
            CarbonImmutable::parse($request->validated('start_at'), config('app.timezone')),
            CarbonImmutable::parse($request->validated('end_at'), config('app.timezone')),
        )->load('room');

        return (new RecordingExtensionResource($recordingSession))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
