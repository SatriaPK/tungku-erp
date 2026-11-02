<?php

use App\Models\Project;
use App\Models\Room;
use App\Models\User;

test('client sees active rooms and only their own projects', function () {
    $client = User::factory()->create(['role' => 'client']);
    $other = User::factory()->create(['role' => 'client']);
    Room::factory()->create(['name' => 'Studio A']);
    Room::factory()->inactive()->create(['name' => 'Studio Tutup']);
    $owned = Project::factory()->create(['client_id' => $client->id, 'name' => 'Single Saya']);
    $outside = Project::factory()->create(['client_id' => $other->id, 'name' => 'Proyek Lain']);

    $this->actingAs($client)->getJson('/api/v1/client/rooms')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonFragment(['name' => 'Studio A']);
    $this->getJson('/api/v1/client/projects')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $owned->id)
        ->assertDontSee($outside->name);
});

test('workspace requires an authenticated client', function () {
    $this->getJson('/api/v1/client/rooms')->assertUnauthorized();
    $this->getJson('/api/v1/client/projects')->assertUnauthorized();
    $manager = User::factory()->create(['role' => 'manager']);
    $this->actingAs($manager)->getJson('/api/v1/client/rooms')->assertForbidden();
    $this->getJson('/api/v1/client/projects')->assertForbidden();
});
