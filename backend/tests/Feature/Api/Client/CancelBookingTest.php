<?php

use App\Enum\SessionStatus;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\StudioSettingSeeder;

beforeEach(function () {
    $this->seed(StudioSettingSeeder::class);
    $this->client = User::factory()->client()->create();
    $this->room = Room::factory()->create();
});

test('cancels a held booking', function () {
    $session = RecordingSession::factory()->for($this->room)->for($this->client, 'client')->held(15)->create();

    $this->actingAs($this->client)
        ->deleteJson("/api/v1/client/bookings/{$session->id}", ['reason' => 'Changed my schedule'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.held_until', null)
        ->assertJsonPath('data.room.id', $this->room->id);

    $this->assertDatabaseHas('recording_sessions', [
        'id' => $session->id,
        'status' => SessionStatus::Cancelled->value,
        'held_until' => null,
    ]);
});

test('cancels a confirmed booking', function () {
    $session = RecordingSession::factory()->for($this->room)->for($this->client, 'client')->confirmed()->create();

    $this->actingAs($this->client)
        ->deleteJson("/api/v1/client/bookings/{$session->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('can cancel an expired hold', function () {
    $session = RecordingSession::factory()->for($this->room)->for($this->client, 'client')->held(-1)->create();

    $this->actingAs($this->client)
        ->deleteJson("/api/v1/client/bookings/{$session->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('cannot cancel a completed booking', function () {
    $session = RecordingSession::factory()->for($this->room)->for($this->client, 'client')->completed()->create();

    $this->actingAs($this->client)
        ->deleteJson("/api/v1/client/bookings/{$session->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id']);
});

test('client cannot cancel another clients booking', function () {
    $owner = User::factory()->client()->create();
    $session = RecordingSession::factory()->for($this->room)->for($owner, 'client')->confirmed()->create();

    $this->actingAs($this->client)
        ->deleteJson("/api/v1/client/bookings/{$session->id}")
        ->assertForbidden();
});

test('unknown booking returns not found', function () {
    $this->actingAs($this->client)
        ->deleteJson('/api/v1/client/bookings/999999')
        ->assertNotFound();
});

test('reason must be a string of at most 1000 characters', function () {
    $session = RecordingSession::factory()->for($this->room)->for($this->client, 'client')->confirmed()->create();

    $this->actingAs($this->client)
        ->deleteJson("/api/v1/client/bookings/{$session->id}", ['reason' => str_repeat('x', 1001)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

test('unauthenticated request returns 401', function () {
    $this->deleteJson('/api/v1/client/bookings/1')->assertUnauthorized();
});

test('inactive user returns 403', function () {
    $inactive = User::factory()->client()->inactive()->create();

    $this->actingAs($inactive)
        ->deleteJson('/api/v1/client/bookings/1')
        ->assertForbidden();
});
