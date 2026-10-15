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

function createSubmittableProgress(User $operator): array
{
    $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
    $stage = ProjectStage::query()->create([
        'project_id' => $project->id,
        'stage' => 'editing',
        'sequence' => 1,
        'operator_id' => $operator->id,
        'status' => StageStatus::InProgress,
        'started_at' => now(),
    ]);
    $track = ProjectTrack::query()->create([
        'project_id' => $project->id,
        'title' => 'Track One',
        'position' => 1,
    ]);
    $progress = TrackStageProgress::query()->create([
        'project_stage_id' => $stage->id,
        'project_track_id' => $track->id,
        'status' => TrackProgressStatus::Pending,
    ]);

    return [$project, $stage, $track, $progress];
}

it('stores each submission as a new immutable version and moves progress to review', function () {
    $operator = User::factory()->operator()->create();
    [, , $track, $progress] = createSubmittableProgress($operator);

    $first = $this->actingAs($operator)->postJson("/api/v1/operator/track-progress/{$progress->id}/tracks/{$track->id}/submissions", [
        'result_url' => 'https://drive.google.com/file/v1',
    ])->assertCreated();

    expect($first->json('data.version'))->toBe(1);
    $progress->refresh()->update(['status' => TrackProgressStatus::Revision]);

    $this->postJson("/api/v1/operator/track-progress/{$progress->id}/tracks/{$track->id}/submissions", [
        'result_url' => 'https://drive.google.com/file/v2',
    ])->assertCreated()->assertJsonPath('data.version', 2);

    expect($progress->fresh()->current_version)->toBe(2)
        ->and($progress->fresh()->result_url)->toBe('https://drive.google.com/file/v2')
        ->and($progress->fresh()->status)->toBe(TrackProgressStatus::InReview)
        ->and(TrackStageSubmission::query()->where('track_stage_progress_id', $progress->id)->orderBy('version')->pluck('result_url')->all())
        ->toBe(['https://drive.google.com/file/v1', 'https://drive.google.com/file/v2']);
});

it('rejects an unassigned operator, foreign track, invalid URL, inactive project and completed stage', function () {
    $operator = User::factory()->operator()->create();
    $otherOperator = User::factory()->operator()->create();
    [$project, $stage, $track, $progress] = createSubmittableProgress($operator);
    $endpoint = "/api/v1/operator/track-progress/{$progress->id}/tracks/{$track->id}/submissions";

    $this->actingAs($otherOperator)->postJson($endpoint, ['result_url' => 'https://example.com/result'])->assertForbidden();
    $this->actingAs($operator)->postJson($endpoint, ['result_url' => 'javascript:alert(1)'])->assertUnprocessable();

    $foreignTrack = ProjectTrack::query()->create(['project_id' => Project::factory()->create()->id, 'title' => 'Foreign', 'position' => 1]);
    $this->postJson("/api/v1/operator/track-progress/{$progress->id}/tracks/{$foreignTrack->id}/submissions", ['result_url' => 'https://example.com/result'])->assertUnprocessable();

    $project->update(['status' => ProjectStatus::Cancelled]);
    $this->postJson($endpoint, ['result_url' => 'https://example.com/result'])->assertUnprocessable();
    $project->update(['status' => ProjectStatus::InProgress]);
    $stage->update(['status' => StageStatus::Completed]);
    $this->postJson($endpoint, ['result_url' => 'https://example.com/result'])->assertUnprocessable();

    expect(TrackStageSubmission::query()->count())->toBe(0);
});

it('does not allow clients or managers to submit results', function () {
    $operator = User::factory()->operator()->create();
    [, , $track, $progress] = createSubmittableProgress($operator);
    $endpoint = "/api/v1/operator/track-progress/{$progress->id}/tracks/{$track->id}/submissions";

    $this->actingAs(User::factory()->manager()->create())->postJson($endpoint, ['result_url' => 'https://example.com/result'])->assertForbidden();
    $this->actingAs(User::factory()->client()->create())->postJson($endpoint, ['result_url' => 'https://example.com/result'])->assertForbidden();
});
