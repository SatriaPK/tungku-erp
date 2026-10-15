<?php

use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\TrackProgressStatus;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\TrackStageSubmission;
use App\Models\User;

function createOperatorHistoryProgress(User $operator): array
{
    $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
    $stage = ProjectStage::query()->create(['project_id' => $project->id, 'stage' => 'editing', 'sequence' => 1, 'operator_id' => $operator->id, 'status' => StageStatus::InProgress, 'started_at' => now()]);
    $track = ProjectTrack::query()->create(['project_id' => $project->id, 'title' => 'Track', 'position' => 1]);
    $progress = TrackStageProgress::query()->create(['project_stage_id' => $stage->id, 'project_track_id' => $track->id, 'status' => TrackProgressStatus::Pending]);

    return [$project, $stage, $track, $progress];
}

it('returns assigned operator history in version order and hides it from other roles', function () {
    $operator = User::factory()->operator()->create();
    [, , $track, $progress] = createOperatorHistoryProgress($operator);
    $first = $progress->submissions()->create(['version' => 1, 'result_url' => 'https://example.com/v1', 'submitted_by' => $operator->id, 'submitted_at' => now()]);
    $second = $progress->submissions()->create(['version' => 2, 'result_url' => 'https://example.com/v2', 'submitted_by' => $operator->id, 'submitted_at' => now()]);
    $endpoint = "/api/v1/operator/track-progress/{$progress->id}/submissions";

    $this->actingAs($operator)->getJson($endpoint)->assertOk()
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.1.id', $second->id)
        ->assertJsonPath('data.0.submitted_by.id', $operator->id);

    $this->actingAs(User::factory()->operator()->create())->getJson($endpoint)->assertForbidden();
    $this->actingAs(User::factory()->manager()->create())->getJson($endpoint)->assertForbidden();
    $this->actingAs(User::factory()->client()->create())->getJson($endpoint)->assertForbidden();
    expect(TrackStageSubmission::query()->count())->toBe(2);
});

it('returns an empty history for an assigned operator', function () {
    $operator = User::factory()->operator()->create();
    [, , , $progress] = createOperatorHistoryProgress($operator);

    $this->actingAs($operator)->getJson("/api/v1/operator/track-progress/{$progress->id}/submissions")
        ->assertOk()->assertJsonPath('data', []);
});
