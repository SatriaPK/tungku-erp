<?php

use App\Http\Resources\AvailabilityResource;
use App\Http\Resources\RecordingSessionListResource;
use App\Http\Resources\RecordingSessionResource;
use App\Http\Resources\RoomResource;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\StudioSettingSeeder;

beforeEach(function () {
    $this->seed(StudioSettingSeeder::class);
    $this->room = Room::factory()->create(['name' => 'Studio A']);
    $this->client = User::factory()->create(['name' => 'John Doe']);
});

test('RoomResource returns correct structure', function () {
    $resource = new RoomResource($this->room);
    $array = $resource->toArray(request());

    expect($array)->toHaveKeys(['id', 'name', 'is_active'])
        ->and($array['name'])->toBe('Studio A')
        ->and($array['is_active'])->toBe(true);
});

test('RecordingSessionResource returns correct structure', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->confirmed()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $session->load(['room', 'client']);
    $resource = new RecordingSessionResource($session);
    $array = $resource->toArray(request());

    expect($array)->toHaveKeys([
        'id', 'room', 'client', 'start_at', 'end_at',
        'status', 'status_label', 'held_until',
        'project_id', 'project_stage_id', 'created_at', 'updated_at',
    ])
        ->and($array['status'])->toBe('confirmed')
        ->and($array['room'])->not->toBeNull()
        ->and($array['client'])->not->toBeNull();
});

test('RecordingSessionResource formats dates as ISO 8601', function () {
    config()->set('app.timezone', 'Asia/Jakarta');

    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->confirmed()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $resource = new RecordingSessionResource($session);
    $array = $resource->toArray(request());

    expect($array['start_at'])->toBeString()
        ->and($array['start_at'])->toBe('2026-10-10T10:00:00+07:00')
        ->and($array['end_at'])->toBe('2026-10-10T12:00:00+07:00');
});

test('RecordingSessionListResource excludes nested relations', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->confirmed()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $session->load(['room', 'client']);
    $resource = new RecordingSessionListResource($session);
    $array = $resource->toArray(request());

    expect($array)->toHaveKeys(['id', 'room_id', 'room_name', 'client_name', 'start_at', 'end_at', 'status'])
        ->and($array)->not->toHaveKey('room')
        ->and($array)->not->toHaveKey('client')
        ->and($array['room_name'])->toBe('Studio A');
});

test('AvailabilityResource shows available status', function () {
    $data = [
        'available' => true,
        'reason' => null,
        'requested_start' => '2026-10-10T10:00:00+07:00',
        'requested_end' => '2026-10-10T12:00:00+07:00',
        'room_id' => 1,
    ];

    $resource = new AvailabilityResource($data);
    $array = $resource->toArray(request());

    expect($array['available'])->toBeTrue()
        ->and($array['reason'])->toBeNull();
});

test('AvailabilityResource shows unavailable with reason', function () {
    $data = [
        'available' => false,
        'reason' => 'Slot already booked',
        'requested_start' => '2026-10-10T10:00:00+07:00',
        'requested_end' => '2026-10-10T12:00:00+07:00',
        'room_id' => 1,
    ];

    $resource = new AvailabilityResource($data);
    $array = $resource->toArray(request());

    expect($array['available'])->toBeFalse()
        ->and($array['reason'])->toBe('Slot already booked');
});
