<?php

namespace App\Actions\Project;

use App\Models\Project;
use App\Models\TrackStageProgress;
use Illuminate\Support\Collection;

class LoadProjectCardProgress
{
    /** @param Collection<int, Project> $projects */
    public function execute(Collection $projects, ?string $stageValue = null): void
    {
        $projectIds = $projects->pluck('id')->all();
        $includedStageValuesByProject = $projects->mapWithKeys(fn (Project $project): array => [
            $project->id => $stageValue === null ? $project->includedStageValues() : [$stageValue],
        ]);
        $progressByProject = TrackStageProgress::query()
            ->selectRaw('project_stages.project_id, project_stages.stage, COUNT(*) as total_count, SUM(CASE WHEN track_stage_progress.status = ? THEN 1 ELSE 0 END) as approved_count', ['approved'])
            ->join('project_stages', 'project_stages.id', '=', 'track_stage_progress.project_stage_id')
            ->whereIn('project_stages.project_id', $projectIds)
            ->groupBy('project_stages.project_id', 'project_stages.stage')
            ->get()
            ->groupBy('project_id');

        $projects->each(function (Project $project) use ($includedStageValuesByProject, $progressByProject, $stageValue): void {
            $includedStageValues = $includedStageValuesByProject->get($project->id, []);
            $matchingStageProgress = collect($progressByProject->get($project->id, []))->whereIn('stage', $includedStageValues);
            $approvedCount = $matchingStageProgress->sum(fn ($row) => (int) $row->approved_count);
            $total = $stageValue === null
                ? $matchingStageProgress->sum(fn ($row) => (int) $row->total_count)
                : $project->tracks_count;
            $project->setAttribute('progress_percent', $total === 0 ? 0 : (int) round($approvedCount / $total * 100));
            $project->setAttribute('tracks_approved_count', (int) $approvedCount);
        });
    }
}
