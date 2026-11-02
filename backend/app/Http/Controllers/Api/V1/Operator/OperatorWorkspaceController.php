<?php

namespace App\Http\Controllers\Api\V1\Operator;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\ProjectStage;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class OperatorWorkspaceController extends Controller
{
    public function rooms(): AnonymousResourceCollection
    {
        return RoomResource::collection(Room::query()->active()->orderBy('name')->get());
    }

    public function stages(Request $request): AnonymousResourceCollection
    {
        $stages = ProjectStage::query()
            ->where('operator_id', $request->user()->id)
            ->with(['project.client:id,name', 'trackProgress.track:id,title'])
            ->latest('assigned_at')
            ->paginate(15);

        return JsonResource::collection($stages->through(fn (ProjectStage $stage): array => [
            'id' => $stage->id,
            'project_id' => $stage->project_id,
            'project_name' => $stage->project?->name,
            'client_name' => $stage->project?->client?->name,
            'stage' => $stage->stage->value,
            'status' => $stage->status->value,
            'deadline_at' => $stage->deadline_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'tracks' => $stage->trackProgress->map(fn ($progress): array => [
                'id' => $progress->track?->id,
                'title' => $progress->track?->title,
                'progress_id' => $progress->id,
                'status' => $progress->status->value,
                'review_notes' => $progress->review_notes,
            ])->values(),
        ]));
    }
}
