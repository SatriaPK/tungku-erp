<?php

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\TrackStageSubmission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();
    $this->client = User::factory()->create();

    $this->project = Project::create([
        'code' => 'PRJ-TEST-001',
        'client_id' => $this->client->id,
        'name' => 'Test Project',
        'package_snapshot' => ['name' => 'Test Package'],
        'price' => 1_000_000,
        'po_amount' => 500_000,
        'status' => 'in_progress',
    ]);

    $this->track = ProjectTrack::create([
        'project_id' => $this->project->id,
        'title' => 'Lagu Satu',
        'position' => 1,
    ]);

    $this->stage = ProjectStage::create([
        'project_id' => $this->project->id,
        'stage' => 'editing',
        'sequence' => 2,
        'operator_id' => $this->operator->id,
        'status' => 'in_progress',
    ]);

    $this->progress = TrackStageProgress::create([
        'project_stage_id' => $this->stage->id,
        'project_track_id' => $this->track->id,
        'status' => 'pending',
    ]);
});

test('can create a submission', function () {
    $sub = TrackStageSubmission::create([
        'track_stage_progress_id' => $this->progress->id,
        'version' => 1,
        'result_url' => 'https://drive.google.com/file/v1',
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    expect($sub->exists)->toBeTrue()
        ->and($sub->version)->toBe(1);
});

test('submission belongs to progress', function () {
    $sub = TrackStageSubmission::create([
        'track_stage_progress_id' => $this->progress->id,
        'version' => 1,
        'result_url' => 'https://drive.google.com/file/v1',
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    expect($sub->progress->id)->toBe($this->progress->id);
});

test('progress has many submissions', function () {
    TrackStageSubmission::create([
        'track_stage_progress_id' => $this->progress->id,
        'version' => 1,
        'result_url' => 'https://drive.google.com/file/v1',
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    TrackStageSubmission::create([
        'track_stage_progress_id' => $this->progress->id,
        'version' => 2,
        'result_url' => 'https://drive.google.com/file/v2',
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    expect($this->progress->submissions)->toHaveCount(2);
});

test('can create 3 versions', function () {
    foreach ([1, 2, 3] as $v) {
        TrackStageSubmission::create([
            'track_stage_progress_id' => $this->progress->id,
            'version' => $v,
            'result_url' => "https://drive.google.com/file/v{$v}",
            'submitted_by' => $this->operator->id,
            'submitted_at' => now(),
        ]);
    }

    expect($this->progress->submissions)->toHaveCount(3);
});

test('unique constraint on version', function () {
    TrackStageSubmission::create([
        'track_stage_progress_id' => $this->progress->id,
        'version' => 1,
        'result_url' => 'https://drive.google.com/file/v1',
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    TrackStageSubmission::create([
        'track_stage_progress_id' => $this->progress->id,
        'version' => 1,
        'result_url' => 'https://drive.google.com/file/v1-dup',
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);
})->throws(QueryException::class);

test('same version for different progress', function () {
    $track2 = ProjectTrack::create([
        'project_id' => $this->project->id,
        'title' => 'Lagu Dua',
        'position' => 2,
    ]);

    $progress2 = TrackStageProgress::create([
        'project_stage_id' => $this->stage->id,
        'project_track_id' => $track2->id,
        'status' => 'pending',
    ]);

    $sub1 = TrackStageSubmission::create([
        'track_stage_progress_id' => $this->progress->id,
        'version' => 1,
        'result_url' => 'https://drive.google.com/file/v1',
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    $sub2 = TrackStageSubmission::create([
        'track_stage_progress_id' => $progress2->id,
        'version' => 1,
        'result_url' => 'https://drive.google.com/file/v1',
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    expect($sub1->exists)->toBeTrue()
        ->and($sub2->exists)->toBeTrue();
});

test('current_version can be updated', function () {
    $this->progress->update(['current_version' => 3]);
    expect($this->progress->fresh()->current_version)->toBe(3);
});

test('cascade delete submissions', function () {
    TrackStageSubmission::create([
        'track_stage_progress_id' => $this->progress->id,
        'version' => 1,
        'result_url' => 'https://drive.google.com/file/v1',
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    $id = $this->progress->id;
    $this->progress->delete();

    expect(TrackStageSubmission::where('track_stage_progress_id', $id)->count())->toBe(0);
});

test('submitted_at is datetime', function () {
    $sub = TrackStageSubmission::create([
        'track_stage_progress_id' => $this->progress->id,
        'version' => 1,
        'result_url' => 'https://drive.google.com/file/v1',
        'submitted_by' => $this->operator->id,
        'submitted_at' => '2026-10-08 22:00:00',
    ]);

    expect($sub->submitted_at)->toBeInstanceOf(Carbon::class);
});
