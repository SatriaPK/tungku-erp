<?php

use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Enum\TrackProgressStatus;
use App\Models\Project;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->manager()->create());
});

it('returns all project statuses in a safe paginated project card resource', function () {
    $client = User::factory()->client()->create(['name' => 'Klien Aruna', 'email' => 'private@example.test']);
    $operator = User::factory()->operator()->create(['name' => 'Operator Bima', 'email' => 'operator-private@example.test']);

    $inProgress = Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'Album Senja',
        'status' => ProjectStatus::InProgress,
        'created_at' => '2026-10-22 10:00:00',
    ]);
    $recording = $inProgress->stages()->create([
        'stage' => StageType::Recording,
        'sequence' => StageType::Recording->sequence(),
        'operator_id' => $operator->id,
        'status' => StageStatus::InProgress,
        'started_at' => '2026-10-21 09:00:00',
    ]);
    $trackOne = ProjectTrack::create(['project_id' => $inProgress->id, 'title' => 'Lagu satu', 'position' => 1]);
    $trackTwo = ProjectTrack::create(['project_id' => $inProgress->id, 'title' => 'Lagu dua', 'position' => 2]);
    foreach ([$trackOne, $trackTwo] as $track) {
        TrackStageProgress::create([
            'project_stage_id' => $recording->id,
            'project_track_id' => $track->id,
            'status' => TrackProgressStatus::InReview,
        ]);
    }
    $recording->trackProgress()->where('project_track_id', $trackOne->id)->update(['status' => TrackProgressStatus::Approved->value]);

    $completed = Project::factory()->create([
        'name' => 'Project Selesai',
        'status' => ProjectStatus::Completed,
        'created_at' => '2026-10-21 10:00:00',
    ]);
    $cancelled = Project::factory()->create(['name' => 'Project Batal', 'status' => ProjectStatus::Cancelled, 'created_at' => '2026-10-23 10:00:00']);

    $response = $this->getJson('/api/v1/manager/projects?per_page=2')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2);
    expect($response->json('data'))->toHaveCount(2);

    $nextPage = $this->getJson('/api/v1/manager/projects?per_page=2&page=2')
        ->assertOk();

    $project = collect($nextPage->json('data'))
        ->merge($response->json('data'))
        ->firstWhere('id', $inProgress->id);
    expect($project)->not->toBeNull()
        ->and($project['client']['id'])->toBe($client->id)
        ->and($project['current_stage']['stage'])->toBe('recording')
        ->and($project['current_stage']['operator']['id'])->toBe($operator->id)
        ->and($project['started_at'])->toBe('2026-10-21T09:00:00+07:00');

    expect(json_encode([$response->json(), $nextPage->json()]))
        ->not->toContain('private@example.test', 'operator-private@example.test', 'review_notes', 'result_url', 'password');
});

it('filters projects by search, status, and current production stage and supports each requested order', function () {
    $client = User::factory()->client()->create(['name' => 'Klien Filter']);
    $recording = Project::factory()->create(['client_id' => $client->id, 'name' => 'Aruna Album', 'status' => ProjectStatus::InProgress]);
    $recording->stages()->create(['stage' => StageType::Recording, 'sequence' => 1, 'status' => StageStatus::Ready]);
    $editing = Project::factory()->create(['client_id' => $client->id, 'name' => 'Bima Single', 'status' => ProjectStatus::InProgress]);
    $editing->stages()->create(['stage' => StageType::Editing, 'sequence' => 2, 'status' => StageStatus::InProgress]);
    Project::factory()->create(['client_id' => $client->id, 'name' => 'Citra Pending', 'status' => ProjectStatus::PendingPayment]);

    $this->getJson('/api/v1/manager/projects?search=Aruna')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $recording->id);

    $this->getJson('/api/v1/manager/projects?status=in_progress&stage=editing')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $editing->id);

    $this->getJson('/api/v1/manager/projects?sort=name_asc')
        ->assertOk()->assertJsonPath('data.0.name', 'Aruna Album');

    $this->getJson('/api/v1/manager/projects?sort=name_desc')
        ->assertOk()->assertJsonPath('data.0.name', 'Citra Pending');
});

it('validates project list filters and protects the endpoint from non-manager roles', function () {
    $this->getJson('/api/v1/manager/projects?status=unknown')->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->getJson('/api/v1/manager/projects?stage=mastered')->assertUnprocessable()->assertJsonValidationErrors('stage');
    $this->getJson('/api/v1/manager/projects?sort=arbitrary')->assertUnprocessable()->assertJsonValidationErrors('sort');

    Sanctum::actingAs(User::factory()->operator()->create());
    $this->getJson('/api/v1/manager/projects')->assertForbidden();

    Sanctum::actingAs(User::factory()->client()->create());
    $this->getJson('/api/v1/manager/projects')->assertForbidden();
});
