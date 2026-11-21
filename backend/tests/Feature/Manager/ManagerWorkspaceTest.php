<?php

use App\Models\Project;
use App\Models\User;

test('manager can browse projects, invoices, and active operators', function () {
    $manager = User::factory()->create(['role' => 'manager']);
    $operator = User::factory()->create(['role' => 'operator', 'name' => 'Operator Aktif']);
    User::factory()->create(['role' => 'operator', 'is_active' => false, 'name' => 'Operator Nonaktif']);
    $project = Project::factory()->create(['name' => 'Album Studio']);
    $client = User::factory()->create(['role' => 'client', 'name' => 'Klien Aktif']);

    $this->actingAs($manager)->getJson('/api/v1/manager/projects')
        ->assertOk()->assertJsonFragment(['name' => $project->name]);
    $this->getJson('/api/v1/manager/operators')->assertOk()
        ->assertJsonFragment(['id' => $operator->id, 'name' => 'Operator Aktif'])
        ->assertDontSee('Operator Nonaktif');
    $this->getJson('/api/v1/manager/invoices')->assertOk();
    $this->getJson('/api/v1/manager/clients')->assertOk()->assertJsonFragment(['id' => $client->id, 'name' => 'Klien Aktif']);
});

test('non managers cannot read manager workspace', function () {
    $client = User::factory()->create(['role' => 'client']);
    $this->actingAs($client)->getJson('/api/v1/manager/projects')->assertForbidden();
    $this->getJson('/api/v1/manager/operators')->assertForbidden();
    $this->getJson('/api/v1/manager/invoices')->assertForbidden();
});
