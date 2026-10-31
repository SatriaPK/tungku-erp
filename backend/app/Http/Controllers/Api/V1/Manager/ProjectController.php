<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\ProjectIndexRequest;
use App\Http\Resources\Manager\ProjectIndexResource;
use App\Models\Project;
use App\Actions\Project\LoadProjectCardProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** @tags Project */
class ProjectController extends Controller
{
    /**
     * List projects for Manager project cards. Newest and oldest sorting use project creation date.
     */
    public function index(ProjectIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $stageValue = $filters['stage'] ?? null;

        $projects = Project::query()
            ->select('projects.*')
            ->with(['client:id,name', 'currentStage.operator:id,name'])
            ->withCount('tracks')
            ->when(isset($filters['search']), fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', '%'.$filters['search'].'%'));
            }))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($stageValue !== null, fn (Builder $query) => $query->whereHas('stages', fn (Builder $stage) => $stage->where('stage', $stageValue)))
            ->when(($filters['sort'] ?? 'newest') === 'oldest', fn (Builder $query) => $query->orderBy('created_at')->orderBy('id'), function (Builder $query) use ($filters): void {
                if (($filters['sort'] ?? 'newest') === 'name_asc') {
                    $query->orderBy('name')->orderBy('id');
                } elseif (($filters['sort'] ?? 'newest') === 'name_desc') {
                    $query->orderByDesc('name')->orderByDesc('id');
                } else {
                    $query->orderByDesc('created_at')->orderByDesc('id');
                }
            })
            ->paginate($filters['per_page'] ?? 15);

        app(LoadProjectCardProgress::class)->execute($projects->getCollection(), $stageValue);

        return ProjectIndexResource::collection($projects);
    }
}
