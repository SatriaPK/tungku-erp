<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceListResource;
use App\Http\Resources\UserResource;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagerWorkspaceController extends Controller
{
    public function projects(Request $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->with(['client:id,name,email', 'package:id,name', 'stages.operator:id,name', 'stages.trackProgress.track:id,title', 'stages.trackProgress.submissions'])
            ->latest()
            ->paginate(15);

        return JsonResource::collection($projects->through(fn (Project $project): array => [
            'id' => $project->id,
            'code' => $project->code,
            'name' => $project->name,
            'client' => $project->client ? ['id' => $project->client->id, 'name' => $project->client->name] : null,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'price' => $project->price,
            'package_name' => $project->package_snapshot['name'] ?? $project->package?->name,
            'stages' => $project->stages->map(fn ($stage): array => [
                'stage' => $stage->stage->value,
                'status' => $stage->status->value,
                'operator' => $stage->operator ? ['id' => $stage->operator->id, 'name' => $stage->operator->name] : null,
                'tracks' => $stage->trackProgress->map(fn ($progress): array => [
                    'progress_id' => $progress->id,
                    'track_id' => $progress->track?->id,
                    'title' => $progress->track?->title,
                    'status' => $progress->status->value,
                    'result_url' => $progress->result_url,
                    'submission_id' => $progress->submissions->sortByDesc('version')->first()?->id,
                ])->values(),
            ])->values(),
            'created_at' => $project->created_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
        ]));
    }

    public function operators(): AnonymousResourceCollection
    {
        return UserResource::collection(User::query()->operators()->active()->orderBy('name')->get());
    }

    public function clients(): AnonymousResourceCollection
    {
        return UserResource::collection(User::query()->where('role', 'client')->active()->orderBy('name')->get());
    }

    public function invoices(): AnonymousResourceCollection
    {
        return InvoiceListResource::collection(Invoice::query()->with(['project', 'client'])->latest()->paginate(15));
    }
}
