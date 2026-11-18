<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Project;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientWorkspaceController extends Controller
{
    public function rooms(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->isClient(), 403);

        return RoomResource::collection(Room::query()->active()->orderBy('name')->get());
    }

    public function projects(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->isClient(), 403);

        return $this->projectCollection($request);
    }

    private function projectCollection(Request $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->where('client_id', $request->user()->id)
            ->with(['stages:id,project_id,stage,status,sequence', 'package:id,name'])
            ->latest()
            ->paginate(15);

        return JsonResource::collection($projects->through(function (Project $project): array {
            $stages = $project->stages->map(fn ($stage): array => [
                'stage' => $stage->stage->value,
                'status' => $stage->status->value,
            ])->values();

            return [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'price' => $project->price,
                'package_name' => $project->package_snapshot['name'] ?? $project->package?->name,
                'stages' => $stages,
                'completed_at' => $project->completed_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
                'created_at' => $project->created_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            ];
        }));
    }
}
