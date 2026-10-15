<?php

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\User;

function createManagerHistoryProgress(User $operator): array
{
    $project = Project::factory()->create();
    $stage = ProjectStage::query()->create(['project_id' => $project->id, 'stage' => 'editing', 'sequence' => 1, 'operator_id' => $operator->id]);
    $track = ProjectTrack::query()->create(['project_id' => $project->id, 'title' => 'Track', 'position' => 1]);
    $progress = TrackStageProgress::query()->create(['project_stage_id' => $stage->id, 'project_track_id' => $track->id]);

    return [$project, $stage, $track, $progress];
}

it('returns project-scoped manager history with submission metadata', function () {
    $manager = User::factory()->manager()->create();
    $operator = User::factory()->operator()->create();
    [, , $track, $progress] = createManagerHistoryProgress($operator);
    $submission = $progress->submissions()->create([
        'version' => 1,
        'result_url' => 'https://example.com/result',
        'submitted_by' => $operator->id,
        'submitted_at' => now(),
        'review_notes' => 'Looks good.',
        'reviewed_by' => $manager->id,
        'reviewed_at' => now(),
        'review_decision' => 'approve',
    ]);
    $stage = $progress->stage;
    $endpoint = "/api/v1/manager/projects/{$stage->project_id}/stages/{$stage->id}/tracks/{$track->id}/history";

    $this->actingAs($manager)->getJson($endpoint)->assertOk()
        ->assertJsonPath('data.0.id', $submission->id)
        ->assertJsonPath('data.0.review_notes', 'Looks good.')
        ->assertJsonPath('data.0.submitted_by.id', $operator->id)
        ->assertJsonPath('data.0.reviewed_by.id', $manager->id);

    $foreignProject = Project::factory()->create();
    $foreignStage = ProjectStage::query()->create(['project_id' => $foreignProject->id, 'stage' => 'editing', 'sequence' => 1]);
    $foreignTrack = ProjectTrack::query()->create(['project_id' => $foreignProject->id, 'title' => 'Foreign', 'position' => 1]);
    $this->getJson("/api/v1/manager/projects/{$foreignProject->id}/stages/{$foreignStage->id}/tracks/{$foreignTrack->id}/history")
        ->assertNotFound();

    $this->actingAs(User::factory()->client()->create())->getJson($endpoint)->assertForbidden();
});

it('returns empty manager history and rejects mismatched project identifiers', function () {
    $manager = User::factory()->manager()->create();
    $operator = User::factory()->operator()->create();
    [, $stage, $track] = createManagerHistoryProgress($operator);
    $endpoint = "/api/v1/manager/projects/{$stage->project_id}/stages/{$stage->id}/tracks/{$track->id}/history";

    $this->actingAs($manager)->getJson($endpoint)->assertOk()->assertJsonPath('data', []);
    $this->getJson('/api/v1/manager/projects/999999/stages/'.$stage->id.'/tracks/'.$track->id.'/history')->assertNotFound();
});
