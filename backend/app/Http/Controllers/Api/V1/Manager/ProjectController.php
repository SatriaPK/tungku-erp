<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\ProjectIndexRequest;
use App\Http\Resources\Manager\ProjectIndexResource;
use App\Models\Project;
use App\Models\TrackStageProgress;
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

        $projectIds = $projects->getCollection()->modelKeys();
        $includedStageValuesByProject = $projects->getCollection()->mapWithKeys(fn (Project $project): array => [
            $project->id => $stageValue === null ? $project->includedStageValues() : [$stageValue],
        ]);
        $progressByProject = TrackStageProgress::query()
            ->selectRaw('project_stages.project_id, project_stages.stage, COUNT(*) as total_count, SUM(CASE WHEN track_stage_progress.status = ? THEN 1 ELSE 0 END) as approved_count', ['approved'])
            ->join('project_stages', 'project_stages.id', '=', 'track_stage_progress.project_stage_id')
            ->whereIn('project_stages.project_id', $projectIds)
            ->groupBy('project_stages.project_id', 'project_stages.stage')
            ->get()
            ->groupBy('project_id');

        $projects->getCollection()->each(function (Project $project) use ($includedStageValuesByProject, $progressByProject, $stageValue): void {
            $includedStageValues = $includedStageValuesByProject->get($project->id, []);
            $matchingStageProgress = collect($progressByProject->get($project->id, []))->whereIn('stage', $includedStageValues);
            $approvedCount = $matchingStageProgress->sum(fn ($row) => (int) $row->approved_count);
            $total = $stageValue === null
                ? $matchingStageProgress->sum(fn ($row) => (int) $row->total_count)
                : $project->tracks_count;
            $project->setAttribute('progress_percent', $total === 0 ? 0 : (int) round($approvedCount / $total * 100));
            $project->setAttribute('tracks_approved_count', (int) $approvedCount);
        });

        return ProjectIndexResource::collection($projects);
    }
}
