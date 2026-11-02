<?php

use App\Enum\SessionStatus;
use App\Models\Payment;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\StudioSettingSeeder;

beforeEach(function () {
    $this->seed(StudioSettingSeeder::class);
    $this->client = User::factory()->client()->create();
    $this->room = Room::factory()->create();
});

function confirmPayload(int $id): array
{
    return ['recording_session_id' => $id];
}

test('confirms an active held session', function () {
    $session = RecordingSession::factory()->for($this->room)->for($this->client, 'client')->held(15)->create();
    $payment = Payment::factory()->paid()->create();
    $session->update(['payment_id' => $payment->id]);

    $response = $this->actingAs($this->client)->postJson('/api/v1/client/bookings/confirm', confirmPayload($session->id));

    $response->assertOk()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.held_until', null)
        ->assertJsonPath('data.room.id', $this->room->id)
        ->assertJsonPath('data.client.id', $this->client->id);

    $this->assertDatabaseHas('recording_sessions', [
        'id' => $session->id,
        'status' => SessionStatus::Confirmed->value,
        'held_until' => null,
    ]);
});

test('rejects expired hold', function () {
    $session = RecordingSession::factory()->for($this->room)->for($this->client, 'client')->held(-1)->create();

    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/confirm', confirmPayload($session->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recording_session_id']);
});

test('rejects already confirmed session', function () {
    $session = RecordingSession::factory()->for($this->room)->for($this->client, 'client')->confirmed()->create();

    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/confirm', confirmPayload($session->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recording_session_id']);
});

test('client cannot confirm another clients session', function () {
    $owner = User::factory()->client()->create();
    $session = RecordingSession::factory()->for($this->room)->for($owner, 'client')->held(15)->create();

    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/confirm', confirmPayload($session->id))
        ->assertForbidden();
});

test('missing or unknown session is rejected', function () {
    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/confirm', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recording_session_id']);

    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/confirm', confirmPayload(999999))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recording_session_id']);
});

test('unauthenticated request returns 401', function () {
    $this->postJson('/api/v1/client/bookings/confirm', confirmPayload(1))->assertUnauthorized();
});

test('inactive user returns 403', function () {
    $inactive = User::factory()->client()->inactive()->create();

    $this->actingAs($inactive)
        ->postJson('/api/v1/client/bookings/confirm', confirmPayload(1))
        ->assertForbidden();
});
