<?php

use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\TrackProgressStatus;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\TrackStageSubmission;
use App\Models\User;

it('runs a multi-track production workflow through revision approval and package-scoped completion', function () {
    $manager = User::factory()->manager()->create();
    $client = User::factory()->client()->create();
    $recordingOperator = User::factory()->operator()->create();
    $masteringOperator = User::factory()->operator()->create();
    $package = Package::factory()->create([
        'include_recording' => false,
        'include_editing' => false,
        'include_mixing' => false,
        'include_mastering' => true,
    ]);
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'package_id' => $package->id,
        'status' => ProjectStatus::AwaitingAssignment,
        'package_snapshot' => ['stages' => ['editing', 'mastering']],
    ]);
    $recording = $project->stages()->create(['stage' => 'editing', 'sequence' => 1, 'status' => StageStatus::Locked]);
    $mastering = $project->stages()->create(['stage' => 'mastering', 'sequence' => 3, 'status' => StageStatus::Locked]);
    $firstTrack = ProjectTrack::query()->create(['project_id' => $project->id, 'title' => 'Track A', 'position' => 1]);
    $secondTrack = ProjectTrack::query()->create(['project_id' => $project->id, 'title' => 'Track B', 'position' => 2]);

    $this->actingAs($manager)->postJson("/api/v1/manager/projects/{$project->id}/assignments", [
        'assignments' => ['editing' => $recordingOperator->id, 'mastering' => $masteringOperator->id],
    ])->assertOk();

    expect($project->fresh()->status)->toBe(ProjectStatus::InProgress)
        ->and($recording->fresh()->status)->toBe(StageStatus::Ready)
        ->and($mastering->fresh()->status)->toBe(StageStatus::Locked);

    $excludedStage = $project->stages()->create(['stage' => 'recording', 'sequence' => 2, 'status' => StageStatus::Completed]);
    TrackStageProgress::query()->create(['project_stage_id' => $excludedStage->id, 'project_track_id' => $firstTrack->id, 'status' => TrackProgressStatus::Approved]);
    expect($excludedStage->fresh()->operator_id)->toBeNull();

    $deadlineUrl = "/api/v1/operator/stages/{$recording->id}/confirm-deadline";
    $this->actingAs($recordingOperator)->postJson($deadlineUrl, ['deadline_at' => now()->addDays(5)->toIso8601String()])->assertOk();
    $this->postJson($deadlineUrl, ['deadline_at' => now()->addDays(6)->toIso8601String()])->assertUnprocessable();
    $this->actingAs($masteringOperator)->postJson($deadlineUrl, ['deadline_at' => now()->addDays(5)->toIso8601String()])->assertUnprocessable();

    $cells = TrackStageProgress::query()->where('project_stage_id', $recording->id)->get()->keyBy('project_track_id');
    $submit = fn (ProjectTrack $track, string $url) => $this->actingAs($recordingOperator)
        ->postJson("/api/v1/operator/track-progress/{$cells[$track->id]->id}/tracks/{$track->id}/submissions", ['result_url' => $url]);

    $submit($firstTrack, 'https://example.com/track-a-v1')->assertCreated()->assertJsonPath('data.version', 1);
    $submit($secondTrack, 'https://example.com/track-b-v1')->assertCreated()->assertJsonPath('data.version', 1);
    $this->actingAs($manager)->getJson('/api/v1/operator/track-progress/'.$cells[$secondTrack->id]->id.'/submissions')->assertForbidden();

    $firstSubmission = $cells[$firstTrack->id]->submissions()->firstOrFail();
    $secondSubmission = $cells[$secondTrack->id]->submissions()->firstOrFail();
    $reviewUrl = fn (TrackStageProgress $progress, TrackStageSubmission $submission): string => "/api/v1/manager/track-progress/{$progress->id}/submissions/{$submission->id}/review";

    $this->actingAs($manager)->postJson($reviewUrl($cells[$firstTrack->id], $firstSubmission), [
        'decision' => 'request_revision',
        'review_notes' => 'Perbaiki balance track.',
    ])->assertOk()->assertJsonPath('data.status', TrackProgressStatus::Revision->value);
    expect($project->fresh()->progressPercent())->toBe(0)
        ->and($recording->fresh()->status)->toBe(StageStatus::InProgress)
        ->and($mastering->fresh()->status)->toBe(StageStatus::Locked);

    $submit($firstTrack, 'https://example.com/track-a-v2')->assertCreated()->assertJsonPath('data.version', 2);
    $secondVersion = $cells[$firstTrack->id]->fresh()->submissions()->where('version', 2)->firstOrFail();
    $this->actingAs($manager)->postJson($reviewUrl($cells[$firstTrack->id], $firstSubmission), ['decision' => 'approve'])
        ->assertUnprocessable()->assertJsonValidationErrors('submission');
    $this->postJson($reviewUrl($cells[$firstTrack->id], $secondVersion), ['decision' => 'approve'])->assertOk();

    expect($project->fresh()->progressPercent())->toBe(25)
        ->and($recording->fresh()->status)->toBe(StageStatus::InProgress)
        ->and($mastering->fresh()->status)->toBe(StageStatus::Locked);

    $this->actingAs($manager)->postJson($reviewUrl($cells[$secondTrack->id], $secondSubmission), ['decision' => 'approve'])->assertOk();
    expect($project->fresh()->progressPercent())->toBe(50)
        ->and($recording->fresh()->status)->toBe(StageStatus::Completed)
        ->and($mastering->fresh()->status)->toBe(StageStatus::Ready);

    $historyUrl = "/api/v1/manager/projects/{$project->id}/stages/{$recording->id}/tracks/{$firstTrack->id}/history";
    $this->actingAs($manager)->getJson($historyUrl)->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.version', 1)
        ->assertJsonPath('data.0.review_notes', 'Perbaiki balance track.')
        ->assertJsonPath('data.1.version', 2)
        ->assertJsonPath('data.1.review_decision', 'approve');
});
