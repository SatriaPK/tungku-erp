<?php

use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;

test('operator sees only assigned production stages', function () {
    $operator = User::factory()->create(['role' => 'operator']);
    $other = User::factory()->create(['role' => 'operator']);
    $project = Project::factory()->create(['name' => 'Single Baru']);
    ProjectStage::create(['project_id' => $project->id, 'stage' => StageType::Recording, 'sequence' => 1, 'operator_id' => $operator->id, 'status' => StageStatus::Ready]);
    ProjectStage::create(['project_id' => $project->id, 'stage' => StageType::Editing, 'sequence' => 2, 'operator_id' => $other->id, 'status' => StageStatus::Locked]);

    $this->actingAs($operator)->getJson('/api/v1/operator/stages')
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.project_name', 'Single Baru')
        ->assertJsonPath('data.0.stage', 'recording');
});

test('client cannot browse operator stages', function () {
    $client = User::factory()->create(['role' => 'client']);
    $this->actingAs($client)->getJson('/api/v1/operator/stages')->assertForbidden();
});
