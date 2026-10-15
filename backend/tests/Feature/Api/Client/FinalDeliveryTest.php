<?php

use App\Enum\ProjectStatus;
use App\Enum\TrackProgressStatus;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** @return array{Project, TrackStageProgress} */
function createFinalDeliveryProject(ProjectStatus $status = ProjectStatus::Completed): array
{
    $client = User::factory()->client()->create();
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'status' => $status,
        'package_snapshot' => ['stages' => ['recording', 'mixing']],
    ]);
    $track = ProjectTrack::query()->create([
        'project_id' => $project->id,
        'title' => 'Track One',
        'position' => 1,
    ]);
    $stages = [];

    foreach (['recording', 'mixing', 'mastering'] as $index => $stageType) {
        $stage = ProjectStage::query()->create([
            'project_id' => $project->id,
            'stage' => $stageType,
            'sequence' => $index + 1,
            'status' => 'completed',
        ]);
        $progress = TrackStageProgress::query()->create([
            'project_stage_id' => $stage->id,
            'project_track_id' => $track->id,
            'status' => TrackProgressStatus::Approved,
            'current_version' => 1,
        ]);
        $progress->submissions()->create([
            'version' => 1,
            'result_url' => "https://example.com/{$stageType}",
            'submitted_by' => $client->id,
            'submitted_at' => now(),
            'review_decision' => 'approve',
        ]);
        $stages[$stageType] = $progress;
    }

    return [$project, $stages['mixing']];
}

it('returns only the current approved submission for the final included stage to the owning client', function () {
    [$project] = createFinalDeliveryProject();
    Sanctum::actingAs($project->client);

    $this->getJson("/api/v1/client/projects/{$project->id}/final-delivery")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.stage', 'mixing')
        ->assertJsonPath('data.0.result_url', 'https://example.com/mixing')
        ->assertJsonMissing(['result_url' => 'https://example.com/recording'])
        ->assertJsonMissing(['result_url' => 'https://example.com/mastering']);
});

it('rejects final-delivery access before project completion', function () {
    [$project] = createFinalDeliveryProject(ProjectStatus::AwaitingFinalPayment);
    Sanctum::actingAs($project->client);

    $this->getJson("/api/v1/client/projects/{$project->id}/final-delivery")->assertForbidden();
});

it('does not allow another client or internal roles to access a project delivery', function () {
    [$project] = createFinalDeliveryProject();

    Sanctum::actingAs(User::factory()->client()->create());
    $this->getJson("/api/v1/client/projects/{$project->id}/final-delivery")->assertForbidden();

    Sanctum::actingAs(User::factory()->manager()->create());
    $this->getJson("/api/v1/client/projects/{$project->id}/final-delivery")->assertForbidden();
});

it('returns no delivery when no included stages exist', function () {
    [$project] = createFinalDeliveryProject();
    $project->update(['package_snapshot' => ['stages' => []]]);
    Sanctum::actingAs($project->client);

    $this->getJson("/api/v1/client/projects/{$project->id}/final-delivery")->assertNotFound();
});
