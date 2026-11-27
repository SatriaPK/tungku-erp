<?php

use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->manager()->create());
});

it('lets a manager create and search operator accounts without granting elevated roles', function () {
    $id = $this->postJson('/api/v1/manager/operators', [
        'name' => '  Rani Operator  ',
        'email' => ' RANI@EXAMPLE.COM ',
        'phone' => '081234567890',
        'password' => 'studio-secret-123',
        'password_confirmation' => 'studio-secret-123',
        'role' => 'manager',
        'is_active' => false,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Rani Operator')
        ->assertJsonPath('data.email', 'rani@example.com')
        ->assertJsonPath('data.role', 'operator')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.phone', '6281234567890')
        ->json('data.id');

    $this->assertDatabaseHas('users', [
        'id' => $id,
        'role' => 'operator',
        'is_active' => true,
        'crm_consent_at' => null,
    ]);
    $this->assertDatabaseMissing('users', ['id' => $id, 'password' => 'studio-secret-123']);

    $this->getJson('/api/v1/manager/operators?search=rani')
        ->assertOk()
        ->assertJsonPath('data.0.id', $id)
        ->assertJsonPath('data.0.assignments_count', 0)
        ->assertJsonPath('data.0.assigned_projects', [])
        ->assertJsonPath('meta.active_count', 1);
});

it('rejects invalid operator account details', function () {
    User::factory()->operator()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/manager/operators', [
        'name' => '',
        'email' => 'taken@example.com',
        'phone' => '123',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'phone', 'password']);
});

it('allows a manager to deactivate and reactivate only operator accounts', function () {
    $operator = User::factory()->operator()->create();
    $manager = User::factory()->manager()->create();

    $this->patchJson("/api/v1/manager/operators/{$operator->id}/status", ['is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false);
    $this->patchJson("/api/v1/manager/operators/{$operator->id}/status", ['is_active' => true])
        ->assertOk()->assertJsonPath('data.is_active', true);
    $this->patchJson("/api/v1/manager/operators/{$manager->id}/status", ['is_active' => false])->assertNotFound();
    $this->patchJson('/api/v1/manager/operators/999999/status', ['is_active' => false])->assertNotFound();
    $this->patchJson("/api/v1/manager/operators/{$operator->id}/status", ['is_active' => 'no'])
        ->assertUnprocessable()->assertJsonValidationErrors('is_active');
});

it('reports each operators current stage assignments', function () {
    $operator = User::factory()->operator()->create();
    $project = Project::factory()->create();
    foreach ([StageType::Recording, StageType::Editing] as $index => $stage) {
        ProjectStage::create([
            'project_id' => $project->id,
            'stage' => $stage,
            'sequence' => $index + 1,
            'operator_id' => $operator->id,
            'status' => StageStatus::Locked,
        ]);
    }
    $project->forceFill(['status' => 'in_progress'])->save();

    $this->getJson('/api/v1/manager/operators?search='.$operator->name)
        ->assertOk()->assertJsonPath('data.0.assignments_count', 2)
        ->assertJsonPath('data.0.assigned_projects.0', [
            'id' => $project->id,
            'name' => $project->name,
            'status' => 'in_progress',
            'status_label' => 'In Progress',
            'client' => ['id' => $project->client->id, 'name' => $project->client->name, 'avatar_url' => null],
            'current_stage' => [
                'stage' => 'recording', 'stage_label' => 'Recording',
                'status' => 'locked', 'status_label' => StageStatus::Locked->label(),
                'operator' => ['id' => $operator->id, 'name' => $operator->name, 'avatar_url' => null],
            ],
            'progress_percent' => 0,
            'started_at' => null,
            'created_at' => $project->created_at->toIso8601String(),
            'stages' => ['Recording', 'Editing'],
        ]);
});

it('excludes inactive project stages from the current project assignment count', function () {
    $operator = User::factory()->operator()->create();
    $project = Project::factory()->create();
    ProjectStage::create([
        'project_id' => $project->id,
        'stage' => StageType::Recording,
        'sequence' => 1,
        'operator_id' => $operator->id,
        'status' => StageStatus::Locked,
    ]);

    $project->forceFill(['status' => 'completed'])->save();

    $this->getJson('/api/v1/manager/operators?search='.$operator->name)
        ->assertOk()->assertJsonPath('data.0.assignments_count', 0)
        ->assertJsonPath('data.0.assigned_projects', []);
});

it('loads assigned projects with a constant query count as the operator list grows', function () {
    $seedAssignment = function () {
        $operator = User::factory()->operator()->create();
        $project = Project::factory()->create(['status' => 'in_progress']);
        ProjectStage::create([
            'project_id' => $project->id,
            'stage' => StageType::Recording,
            'sequence' => 1,
            'operator_id' => $operator->id,
            'status' => StageStatus::Locked,
        ]);
    };
    $queryCount = function (int $operators): int {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $response = $this->getJson('/api/v1/manager/operators')->assertOk()->assertJsonCount($operators, 'data');
            foreach ($response->json('data') as $operator) {
                expect($operator['assigned_projects'])->toHaveCount(1);
            }

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    };
    $seedAssignment();
    $singleCount = $queryCount(1);
    $seedAssignment();
    $seedAssignment();
    expect($queryCount(3))->toBe($singleCount);
});

it('limits operator management to active managers', function () {
    Sanctum::actingAs(User::factory()->operator()->create());
    $this->getJson('/api/v1/manager/operators')->assertForbidden();
    $this->postJson('/api/v1/manager/operators', [])->assertForbidden();

    Sanctum::actingAs(User::factory()->manager()->inactive()->create());
    $this->getJson('/api/v1/manager/operators')->assertForbidden();
});
