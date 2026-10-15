<?php

use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\StudioSettingSeeder;

beforeEach(function () {
    $this->seed(StudioSettingSeeder::class);
    $this->room = Room::factory()->create();
    $this->client = User::factory()->client()->create();
});

test('returns available when slot is free', function () {
    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/check-availability', [
            'room_id' => $this->room->id,
            'start_at' => now()->addDays(1)->setTime(10, 0)->toIso8601String(),
            'end_at' => now()->addDays(1)->setTime(12, 0)->toIso8601String(),
        ]);

    $response->assertOk()->assertJson(['data' => ['available' => true]]);
});

test('returns unavailable when slot conflicts', function () {
    RecordingSession::factory()
        ->for($this->room)->for($this->client, 'client')->confirmed()
        ->timeRange(now()->addDays(1)->setTime(10, 0), now()->addDays(1)->setTime(12, 0))
        ->create();

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/check-availability', [
            'room_id' => $this->room->id,
            'start_at' => now()->addDays(1)->setTime(11, 0)->toIso8601String(),
            'end_at' => now()->addDays(1)->setTime(13, 0)->toIso8601String(),
        ]);

    $response->assertOk()->assertJson(['data' => ['available' => false]]);
});

test('returns unavailable outside operating hours', function () {
    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/check-availability', [
            'room_id' => $this->room->id,
            'start_at' => now()->addDays(1)->setTime(7, 0)->toIso8601String(),
            'end_at' => now()->addDays(1)->setTime(9, 0)->toIso8601String(),
        ]);

    $response->assertOk()->assertJson(['data' => ['available' => false]]);
});

test('validates required fields', function () {
    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/check-availability', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['room_id', 'start_at', 'end_at']);
});

test('validates room exists', function () {
    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/check-availability', [
            'room_id' => 99999,
            'start_at' => now()->addDays(1)->setTime(10, 0)->toIso8601String(),
            'end_at' => now()->addDays(1)->setTime(12, 0)->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['room_id']);
});

test('validates start_at is future', function () {
    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/check-availability', [
            'room_id' => $this->room->id,
            'start_at' => now()->subHours(1)->toIso8601String(),
            'end_at' => now()->addHours(1)->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_at']);
});

test('validates end_at after start_at', function () {
    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/check-availability', [
            'room_id' => $this->room->id,
            'start_at' => now()->addDays(1)->setTime(12, 0)->toIso8601String(),
            'end_at' => now()->addDays(1)->setTime(10, 0)->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_at']);
});

test('validates minimum 1 hour', function () {
    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/check-availability', [
            'room_id' => $this->room->id,
            'start_at' => now()->addDays(1)->setTime(10, 0)->toIso8601String(),
            'end_at' => now()->addDays(1)->setTime(10, 30)->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_at']);
});

test('validates maximum 12 hours', function () {
    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/check-availability', [
            'room_id' => $this->room->id,
            'start_at' => now()->addDays(1)->setTime(9, 0)->toIso8601String(),
            'end_at' => now()->addDays(1)->setTime(22, 0)->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_at']);
});

test('accepts exactly 1 hour', function () {
    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/check-availability', [
            'room_id' => $this->room->id,
            'start_at' => now()->addDays(1)->setTime(10, 0)->toIso8601String(),
            'end_at' => now()->addDays(1)->setTime(11, 0)->toIso8601String(),
        ])
        ->assertOk();
});

test('accepts exactly 12 hours', function () {
    $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/check-availability', [
            'room_id' => $this->room->id,
            'start_at' => now()->addDays(1)->setTime(9, 0)->toIso8601String(),
            'end_at' => now()->addDays(1)->setTime(21, 0)->toIso8601String(),
        ])
        ->assertOk();
});

test('requires authentication', function () {
    $this->postJson('/api/v1/client/bookings/check-availability', [
        'room_id' => $this->room->id,
        'start_at' => now()->addDays(1)->setTime(10, 0)->toIso8601String(),
        'end_at' => now()->addDays(1)->setTime(12, 0)->toIso8601String(),
    ])->assertUnauthorized();
});

test('inactive user cannot access', function () {
    $inactive = User::factory()->client()->create(['is_active' => false]);

    $this->actingAs($inactive)
        ->postJson('/api/v1/client/bookings/check-availability', [
            'room_id' => $this->room->id,
            'start_at' => now()->addDays(1)->setTime(10, 0)->toIso8601String(),
            'end_at' => now()->addDays(1)->setTime(12, 0)->toIso8601String(),
        ])
        ->assertForbidden();
});
