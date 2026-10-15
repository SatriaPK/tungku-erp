<?php

namespace App\Actions\Project;

use App\Enum\OperatorActivityType;
use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Models\OperatorActivity;
use App\Models\ProjectStage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RecordOverdueStages
{
    public function __construct(private RecordOperatorActivity $activities) {}

    /** @return array{examined: int, recorded: int, skipped: int} */
    public function handle(): array
    {
        $cutoff = CarbonImmutable::now(config('app.timezone'));
        $candidates = DB::table('project_stages')->select('project_stages.id')
            ->where('status', StageStatus::InProgress->value)->whereNull('completed_at')
            ->where('stage', '!=', StageType::Recording->value)->where('deadline_at', '<', $cutoff->toDateTimeString())
            ->whereExists(function (Builder $query): void {
                $query->selectRaw('1')->from('projects')->whereColumn('projects.id', 'project_stages.project_id')
                    ->where('projects.status', ProjectStatus::InProgress->value)->whereNull('projects.completed_at');
            })
            ->whereExists(function (Builder $query): void {
                $query->selectRaw('1')->from('operator_activities')->where('type', OperatorActivityType::DeadlineConfirmed->value)
                    ->whereColumn('source_id', 'project_stages.id')->whereColumn('operator_activities.deadline_at', 'project_stages.deadline_at');
            })
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('operator_activities')->where('type', OperatorActivityType::StageOverdue->value)
                    ->whereColumn('source_id', 'project_stages.id');
            });
        $counts = ['examined' => 0, 'recorded' => 0, 'skipped' => 0];
        foreach ($candidates->lazyById(200) as $candidate) {
            $counts['examined']++;
            $counts[$this->recordStage((int) $candidate->id) ? 'recorded' : 'skipped']++;
        }

        return $counts;
    }

    public function recordStage(int $stageId): bool
    {
        return DB::transaction(function () use ($stageId): bool {
            $stage = ProjectStage::query()->lockForUpdate()->find($stageId);
            if ($stage === null) {
                return false;
            }
            $project = $stage->project()->lockForUpdate()->first();
            $detectedAt = CarbonImmutable::now(config('app.timezone'));
            if ($project === null || $project->status !== ProjectStatus::InProgress || $project->completed_at !== null
                || $stage->status !== StageStatus::InProgress || $stage->completed_at !== null
                || $stage->stage === StageType::Recording || $stage->deadline_at === null || ! $stage->deadline_at->lessThan($detectedAt)) {
                return false;
            }
            $marker = OperatorActivity::query()->where('type', OperatorActivityType::DeadlineConfirmed->value)->where('source_id', $stageId)->first();
            if ($marker === null || $marker->deadline_at === null || ! $marker->deadline_at->equalTo($stage->deadline_at)) {
                return false;
            }

            return $this->activities->stageOverdue($stage, $project, $detectedAt)->wasRecentlyCreated;
        }, 3);
    }
}
