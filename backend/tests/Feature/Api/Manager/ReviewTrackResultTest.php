<?php

use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\TrackProgressStatus;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\TrackStageSubmission;
use App\Models\User;

function createReviewableProgress(): array
{
    $operator = User::factory()->operator()->create();
    $manager = User::factory()->manager()->create();
    $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
    $stage = ProjectStage::query()->create([
        'project_id' => $project->id,
        'stage' => 'editing',
        'sequence' => 1,
        'operator_id' => $operator->id,
        'status' => StageStatus::InProgress,
        'started_at' => now(),
    ]);
    $track = ProjectTrack::query()->create(['project_id' => $project->id, 'title' => 'Track One', 'position' => 1]);
    $progress = TrackStageProgress::query()->create([
        'project_stage_id' => $stage->id,
        'project_track_id' => $track->id,
        'status' => TrackProgressStatus::InReview,
        'current_version' => 1,
        'result_url' => 'https://example.com/result-v1',
        'submitted_at' => now(),
    ]);
    $submission = $progress->submissions()->create([
        'version' => 1,
        'result_url' => 'https://example.com/result-v1',
        'submitted_by' => $operator->id,
        'submitted_at' => now(),
    ]);

    return [$manager, $operator, $project, $stage, $track, $progress, $submission];
}

function reviewProgressEndpoint(TrackStageProgress $progress, TrackStageSubmission $submission): string
{
    return "/api/v1/manager/track-progress/{$progress->id}/submissions/{$submission->id}/review";
}

it('approves the current submission and records manager and review time', function () {
    [$manager, , , , , $progress, $submission] = createReviewableProgress();

    $this->actingAs($manager)->postJson(reviewProgressEndpoint($progress, $submission), [
        'decision' => 'approve',
    ])->assertOk()->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.submission.review_decision', 'approve');

    expect($progress->fresh()->approved_by)->toBe($manager->id)
        ->and($progress->fresh()->approved_at)->not->toBeNull()
        ->and($submission->fresh()->reviewed_by)->toBe($manager->id)
        ->and($submission->fresh()->reviewed_at)->not->toBeNull()
        ->and($progress->stage->fresh()->status)->toBe(StageStatus::Completed);
});

it('opens the next included stage only after every track is approved and remains idempotent', function () {
    [$manager, $operator, $project, $stage, , $progress, $submission] = createReviewableProgress();
    $project->update(['package_snapshot' => ['stages' => ['recording', 'editing', 'mastering']]]);
    $stage->update(['stage' => 'recording', 'sequence' => 1]);
    $nextStage = ProjectStage::query()->create([
        'project_id' => $project->id,
        'stage' => 'mastering',
        'sequence' => 4,
        'operator_id' => $operator->id,
        'status' => StageStatus::Locked,
        'assigned_at' => now(),
    ]);
    $track = $project->tracks()->firstOrFail();
    $nextProgress = TrackStageProgress::query()->create([
        'project_stage_id' => $nextStage->id,
        'project_track_id' => $track->id,
    ]);

    $this->actingAs($manager)->postJson(reviewProgressEndpoint($progress, $submission), ['decision' => 'approve'])
        ->assertOk()->assertJsonPath('data.stage_status', StageStatus::Completed->value);

    expect($nextStage->fresh()->status)->toBe(StageStatus::Ready)
        ->and($project->fresh()->status)->toBe(ProjectStatus::InProgress)
        ->and($nextProgress->fresh()->status)->toBe(TrackProgressStatus::Pending);
});

it('creates one locked final payment only after all package stages are approved', function () {
    [$manager, $operator, $project, $stage, , $progress, $submission] = createReviewableProgress();
    $project->update([
        'price' => 1000000,
        'package_snapshot' => ['stages' => ['recording', 'editing', 'mastering']],
    ]);
    $stage->update(['stage' => 'mastering', 'sequence' => 4]);

    foreach ([['recording', 1], ['editing', 2]] as [$stageType, $sequence]) {
        $earlierStage = ProjectStage::query()->create([
            'project_id' => $project->id,
            'stage' => $stageType,
            'sequence' => $sequence,
            'operator_id' => $operator->id,
            'status' => StageStatus::Completed,
        ]);
        TrackStageProgress::query()->create([
            'project_stage_id' => $earlierStage->id,
            'project_track_id' => $project->tracks()->firstOrFail()->id,
            'status' => TrackProgressStatus::Approved,
        ]);
    }
    $project->payments()->create([
        'type' => PaymentType::Po,
        'amount' => 300000,
        'status' => PaymentStatus::Paid,
        'provider' => 'cash',
        'order_id' => 'PO-'.fake()->unique()->uuid(),
    ]);

    $this->actingAs($manager)->postJson(reviewProgressEndpoint($progress, $submission), ['decision' => 'approve'])->assertOk();

    $finalPayment = $project->payments()->where('type', PaymentType::Final->value)->sole();
    expect($project->fresh()->status)->toBe(ProjectStatus::AwaitingFinalPayment)
        ->and($project->fresh()->final_payment_amount)->toBe(700000)
        ->and($finalPayment->amount)->toBe(700000)
        ->and($project->payments()->where('type', PaymentType::Final->value)->count())->toBe(1);
});

it('does not complete a stage or open its successor while any required track is unapproved', function () {
    [$manager, $operator, $project, $stage, , $progress, $submission] = createReviewableProgress();
    $project->update(['package_snapshot' => ['stages' => ['editing', 'mastering']]]);
    $nextStage = ProjectStage::query()->create([
        'project_id' => $project->id,
        'stage' => 'mastering',
        'sequence' => 4,
        'operator_id' => $operator->id,
        'status' => StageStatus::Locked,
        'assigned_at' => now(),
    ]);
    $otherTrack = ProjectTrack::query()->create(['project_id' => $project->id, 'title' => 'Track Two', 'position' => 2]);
    TrackStageProgress::query()->create(['project_stage_id' => $stage->id, 'project_track_id' => $otherTrack->id]);

    $this->actingAs($manager)->postJson(reviewProgressEndpoint($progress, $submission), ['decision' => 'approve'])->assertOk();

    expect($stage->fresh()->status)->toBe(StageStatus::InProgress)
        ->and($nextStage->fresh()->status)->toBe(StageStatus::Locked);
});

it('requests revision with required notes and increments revision count', function () {
    [$manager, , , , , $progress, $submission] = createReviewableProgress();
    $endpoint = reviewProgressEndpoint($progress, $submission);

    $this->actingAs($manager)->postJson($endpoint, ['decision' => 'request_revision'])
        ->assertUnprocessable()->assertJsonValidationErrors('review_notes');

    $this->postJson($endpoint, ['decision' => 'request_revision', 'review_notes' => 'Perbaiki bagian akhir track.'])
        ->assertOk()->assertJsonPath('data.status', 'revision');

    expect($progress->fresh()->revision_count)->toBe(1)
        ->and($progress->fresh()->review_notes)->toBe('Perbaiki bagian akhir track.')
        ->and($submission->fresh()->review_notes)->toBe('Perbaiki bagian akhir track.')
        ->and($submission->fresh()->review_decision)->toBe('request_revision');
});

it('rejects stale submissions and repeated or invalid reviews without partial changes', function () {
    [$manager, $operator, , , , $progress, $submission] = createReviewableProgress();
    $stale = $progress->submissions()->create([
        'version' => 0,
        'result_url' => 'https://example.com/old',
        'submitted_by' => $operator->id,
        'submitted_at' => now()->subMinute(),
    ]);
    $endpoint = reviewProgressEndpoint($progress, $stale);

    $this->actingAs($manager)->postJson($endpoint, ['decision' => 'approve'])
        ->assertUnprocessable()->assertJsonValidationErrors('submission');
    expect($progress->fresh()->status)->toBe(TrackProgressStatus::InReview);

    $this->postJson(reviewProgressEndpoint($progress, $submission), ['decision' => 'unknown'])
        ->assertUnprocessable()->assertJsonValidationErrors('decision');
    $this->postJson(reviewProgressEndpoint($progress, $submission), ['decision' => 'approve'])->assertOk();
    $this->postJson(reviewProgressEndpoint($progress, $submission), ['decision' => 'request_revision', 'review_notes' => 'Tolong perbaiki detail ini.'])
        ->assertUnprocessable();
});

it('rejects non-manager actors and projects that are no longer active', function () {
    [$manager, , $project, , , $progress, $submission] = createReviewableProgress();
    $endpoint = reviewProgressEndpoint($progress, $submission);

    $this->actingAs(User::factory()->operator()->create())->postJson($endpoint, ['decision' => 'approve'])->assertForbidden();
    $this->actingAs(User::factory()->client()->create())->postJson($endpoint, ['decision' => 'approve'])->assertForbidden();

    $project->update(['status' => ProjectStatus::Cancelled]);
    $this->actingAs($manager)->postJson($endpoint, ['decision' => 'approve'])->assertUnprocessable();
    expect($progress->fresh()->status)->toBe(TrackProgressStatus::InReview)
        ->and($submission->fresh()->review_decision)->toBeNull();
});
