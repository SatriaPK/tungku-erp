<?php

namespace App\Actions\Project;

use App\Enum\OperatorActivityType;
use App\Models\OperatorActivity;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\TrackStageSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class RecordOperatorActivity
{
    public function resultSubmitted(TrackStageSubmission $submission, Project $project, ProjectStage $stage, ProjectTrack $track): OperatorActivity
    {
        return OperatorActivity::query()->firstOrCreate([
            'type' => OperatorActivityType::ResultSubmitted->value, 'source_id' => $submission->id,
        ], function () use ($submission, $project, $stage, $track): array {
            $actor = $submission->submittedBy()->firstOrFail();

            return [
                'actor_id' => $submission->submitted_by, 'operator_id' => $submission->submitted_by,
                'actor_name' => $this->snapshotName($actor->name), 'operator_name' => $this->snapshotName($actor->name),
                'project_id' => $project->id, 'project_name' => $this->snapshotName($project->name),
                'project_stage_id' => $stage->id, 'stage' => $stage->stage,
                'project_track_id' => $track->id, 'track_title' => $this->snapshotName($track->title),
                'submission_id' => $submission->id, 'occurred_at' => $submission->submitted_at, 'recorded_at' => now(),
            ];
        });
    }

    public function deadlineConfirmed(ProjectStage $stage, Project $project, User $operator): OperatorActivity
    {
        return OperatorActivity::query()->firstOrCreate([
            'type' => OperatorActivityType::DeadlineConfirmed->value, 'source_id' => $stage->id,
        ], fn (): array => [
            'actor_id' => $operator->id, 'operator_id' => $operator->id,
            'actor_name' => $this->snapshotName($operator->name), 'operator_name' => $this->snapshotName($operator->name),
            'project_id' => $project->id, 'project_name' => $this->snapshotName($project->name),
            'project_stage_id' => $stage->id, 'stage' => $stage->stage,
            'deadline_at' => $stage->deadline_at, 'occurred_at' => $stage->started_at, 'recorded_at' => now(),
        ]);
    }

    public function stageOverdue(ProjectStage $stage, Project $project, CarbonImmutable $detectedAt): OperatorActivity
    {
        return OperatorActivity::query()->firstOrCreate([
            'type' => OperatorActivityType::StageOverdue->value, 'source_id' => $stage->id,
        ], function () use ($stage, $project, $detectedAt): array {
            $operator = $stage->operator()->first();

            return [
                'actor_id' => null, 'actor_name' => null,
                'operator_id' => $operator?->id, 'operator_name' => $operator === null ? null : $this->snapshotName($operator->name),
                'project_id' => $project->id, 'project_name' => $this->snapshotName($project->name),
                'project_stage_id' => $stage->id, 'stage' => $stage->stage,
                'deadline_at' => $stage->deadline_at, 'occurred_at' => $detectedAt, 'recorded_at' => $detectedAt,
            ];
        });
    }

    private function snapshotName(string $name): string
    {
        return Str::limit(Str::trim(strip_tags($name)), 255, '');
    }
}
