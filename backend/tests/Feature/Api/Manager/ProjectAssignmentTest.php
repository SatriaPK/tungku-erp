<?php

use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\User;

beforeEach(function () {
    $this->manager = User::factory()->manager()->create();
    $this->client = User::factory()->client()->create();
    $this->operatorOne = User::factory()->operator()->create();
    $this->operatorTwo = User::factory()->operator()->create();
    $this->project = createAssignableProject($this->client);
});

function createAssignableProject(User $client, array $stages = ['recording', 'editing']): Project
{
    $package = Package::factory()->create([
        'include_recording' => in_array('recording', $stages, true),
        'include_editing' => in_array('editing', $stages, true),
        'include_mixing' => in_array('mixing', $stages, true),
        'include_mastering' => in_array('mastering', $stages, true),
    ]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'package_id' => $package->id,
        'status' => ProjectStatus::AwaitingAssignment,
        'package_snapshot' => [
            'stages' => $stages,
            ...$package->only(['include_recording', 'include_editing', 'include_mixing', 'include_mastering']),
        ],
    ]);

    foreach ($stages as $index => $stageType) {
        ProjectStage::query()->create([
            'project_id' => $project->id,
            'stage' => $stageType,
            'sequence' => $index + 1,
        ]);
    }

    ProjectTrack::query()->create([
        'project_id' => $project->id,
        'title' => 'Track 1',
        'position' => 1,
    ]);

    return $project;
}

function assignOperatorsPayload(User $first, User $second, array $stages = ['recording', 'editing']): array
{
    return array_combine($stages, [$first->id, $second->id]);
}

test('manager assigns one active operator to each included stage and initializes project work', function () {
    $response = $this->actingAs($this->manager)->postJson("/api/v1/manager/projects/{$this->project->id}/assignments", [
        'assignments' => assignOperatorsPayload($this->operatorOne, $this->operatorTwo),
    ]);

    $response->assertOk()
        ->assertJsonPath('meta.project_id', $this->project->id)
        ->assertJsonPath('meta.project_status', ProjectStatus::InProgress->value)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.stage', 'recording')
        ->assertJsonPath('data.0.operator.id', $this->operatorOne->id)
        ->assertJsonPath('data.0.status', StageStatus::Ready->value)
        ->assertJsonPath('data.1.stage', 'editing')
        ->assertJsonPath('data.1.operator.id', $this->operatorTwo->id)
        ->assertJsonPath('data.1.status', StageStatus::Locked->value);

    expect($this->project->fresh()->status)->toBe(ProjectStatus::InProgress)
        ->and($this->project->tracks()->count())->toBe(1)
        ->and(TrackStageProgress::query()->count())->toBe(2)
        ->and($this->project->stages()->whereNotNull('assigned_at')->count())->toBe(2);
});

test('manager cannot submit empty incomplete duplicate or unknown stage assignments', function () {
    $this->actingAs($this->manager)->postJson("/api/v1/manager/projects/{$this->project->id}/assignments", [
        'assignments' => [],
    ])->assertUnprocessable();

    $this->actingAs($this->manager)->postJson("/api/v1/manager/projects/{$this->project->id}/assignments", [
        'assignments' => ['recording' => $this->operatorOne->id],
    ])->assertUnprocessable();

    $this->actingAs($this->manager)->postJson("/api/v1/manager/projects/{$this->project->id}/assignments", [
        'assignments' => assignOperatorsPayload($this->operatorOne, $this->operatorOne),
    ])->assertUnprocessable();

    $this->actingAs($this->manager)->postJson("/api/v1/manager/projects/{$this->project->id}/assignments", [
        'assignments' => ['recording' => $this->operatorOne->id, 'mixing' => $this->operatorTwo->id],
    ])->assertUnprocessable();

    expect($this->project->fresh()->status)->toBe(ProjectStatus::AwaitingAssignment)
        ->and($this->project->stages()->whereNotNull('operator_id')->count())->toBe(0);
});

test('only active operator accounts can be assigned', function () {
    $clientAccount = User::factory()->client()->create();
    $inactiveOperator = User::factory()->operator()->inactive()->create();
    $unknownUserId = User::query()->max('id') + 100;

    foreach ([$clientAccount, $inactiveOperator] as $invalidOperator) {
        $this->actingAs($this->manager)->postJson("/api/v1/manager/projects/{$this->project->id}/assignments", [
            'assignments' => assignOperatorsPayload($invalidOperator, $this->operatorTwo),
        ])->assertUnprocessable();
    }

    $this->actingAs($this->manager)->postJson("/api/v1/manager/projects/{$this->project->id}/assignments", [
        'assignments' => ['recording' => $unknownUserId, 'editing' => $this->operatorTwo->id],
    ])->assertUnprocessable();

    expect($this->project->fresh()->status)->toBe(ProjectStatus::AwaitingAssignment)
        ->and($this->project->stages()->whereNotNull('operator_id')->count())->toBe(0);
});

test('client and operator cannot access manager assignment endpoint', function () {
    $payload = ['assignments' => assignOperatorsPayload($this->operatorOne, $this->operatorTwo)];

    $this->actingAs($this->client)->postJson("/api/v1/manager/projects/{$this->project->id}/assignments", $payload)
        ->assertForbidden();

    $this->actingAs($this->operatorOne)->postJson("/api/v1/manager/projects/{$this->project->id}/assignments", $payload)
        ->assertForbidden();

    expect($this->project->fresh()->status)->toBe(ProjectStatus::AwaitingAssignment);
});

test('rejects a project that is not awaiting assignment and preserves existing state', function () {
    $this->project->update(['status' => ProjectStatus::InProgress]);

    $this->actingAs($this->manager)->postJson("/api/v1/manager/projects/{$this->project->id}/assignments", [
        'assignments' => assignOperatorsPayload($this->operatorOne, $this->operatorTwo),
    ])->assertUnprocessable();

    expect($this->project->stages()->whereNotNull('operator_id')->count())->toBe(0)
        ->and(TrackStageProgress::query()->count())->toBe(0);
});

test('rejects stages that do not match included snapshot and project stage ids cannot be used as project ids', function () {
    $inconsistentProject = createAssignableProject($this->client, ['recording', 'mixing']);

    // The stored snapshot declares a different included-stage set.
    $inconsistentProject->update(['package_snapshot' => ['stages' => ['recording', 'editing']]]);

    $this->actingAs($this->manager)->postJson("/api/v1/manager/projects/{$inconsistentProject->id}/assignments", [
        'assignments' => ['recording' => $this->operatorOne->id, 'editing' => $this->operatorTwo->id],
    ])->assertUnprocessable();

    $foreignProject = createAssignableProject(User::factory()->client()->create());
    $foreignStageId = $foreignProject->stages()->firstOrFail()->id;

    $this->actingAs($this->manager)->postJson("/api/v1/manager/projects/{$foreignStageId}/assignments", [
        'assignments' => assignOperatorsPayload($this->operatorOne, $this->operatorTwo),
    ])->assertNotFound();
});

test('a stage that has already started cannot be reassigned', function () {
    $stage = $this->project->stages()->firstOrFail();
    $stage->update(['status' => StageStatus::Ready]);

    $this->actingAs($this->manager)->postJson("/api/v1/manager/projects/{$this->project->id}/assignments", [
        'assignments' => assignOperatorsPayload($this->operatorOne, $this->operatorTwo),
    ])->assertUnprocessable();

    expect($stage->fresh()->operator_id)->toBeNull()
        ->and(TrackStageProgress::query()->count())->toBe(0);
});

test('assignment preserves existing track stage progress rows without creating duplicates', function () {
    $this->project->tracks()->get()->each(function (ProjectTrack $track): void {
        foreach ($this->project->stages as $stage) {
            TrackStageProgress::query()->create([
                'project_stage_id' => $stage->id,
                'project_track_id' => $track->id,
            ]);
        }
    });

    $this->actingAs($this->manager)->postJson("/api/v1/manager/projects/{$this->project->id}/assignments", [
        'assignments' => assignOperatorsPayload($this->operatorOne, $this->operatorTwo),
    ])->assertOk();

    expect(TrackStageProgress::query()->count())->toBe(2);
});
