<?php

use App\Actions\Booking\CheckAvailability;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\StudioSetting;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\StudioSettingSeeder;

beforeEach(function () {
    $this->seed(StudioSettingSeeder::class);
    $this->action = new CheckAvailability;
    $this->room = Room::factory()->create();
    $this->manager = User::factory()->manager()->create();
});

test('returns available when no conflicts exist', function () {
    $startAt = Carbon::parse('2026-10-10 10:00:00', 'Asia/Jakarta');
    $endAt = Carbon::parse('2026-10-10 12:00:00', 'Asia/Jakarta');

    $result = $this->action->handle($this->room->id, $startAt, $endAt);

    expect($result['available'])->toBeTrue()
        ->and($result['reason'])->toBeNull();
});

test('returns unavailable when confirmed session conflicts', function () {
    RecordingSession::factory()
        ->for($this->room)
        ->confirmed()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $startAt = Carbon::parse('2026-10-10 11:00:00', 'Asia/Jakarta');
    $endAt = Carbon::parse('2026-10-10 13:00:00', 'Asia/Jakarta');

    $result = $this->action->handle($this->room->id, $startAt, $endAt);

    expect($result['available'])->toBeFalse()
        ->and($result['reason'])->toContain('conflicts');
});

test('returns unavailable when active hold conflicts', function () {
    RecordingSession::factory()
        ->for($this->room)
        ->held(60) // 60 minutes until expiry
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $startAt = Carbon::parse('2026-10-10 11:00:00', 'Asia/Jakarta');
    $endAt = Carbon::parse('2026-10-10 13:00:00', 'Asia/Jakarta');

    $result = $this->action->handle($this->room->id, $startAt, $endAt);

    expect($result['available'])->toBeFalse()
        ->and($result['reason'])->toContain('conflicts');
});

test('returns available when expired hold does not block', function () {
    RecordingSession::factory()
        ->for($this->room)
        ->held(-60) // Expired 60 minutes ago
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $startAt = Carbon::parse('2026-10-10 11:00:00', 'Asia/Jakarta');
    $endAt = Carbon::parse('2026-10-10 13:00:00', 'Asia/Jakarta');

    $result = $this->action->handle($this->room->id, $startAt, $endAt);

    expect($result['available'])->toBeTrue()
        ->and($result['reason'])->toBeNull();
});

test('returns available when cancelled session does not block', function () {
    RecordingSession::factory()
        ->for($this->room)
        ->cancelled()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $startAt = Carbon::parse('2026-10-10 11:00:00', 'Asia/Jakarta');
    $endAt = Carbon::parse('2026-10-10 13:00:00');

    $result = $this->action->handle($this->room->id, $startAt, $endAt);

    expect($result['available'])->toBeTrue()
        ->and($result['reason'])->toBeNull();
});

test('returns unavailable when outside operating hours (before open)', function () {
    $startAt = Carbon::parse('2026-10-10 08:00:00', 'Asia/Jakarta');
    $endAt = Carbon::parse('2026-10-10 10:00:00', 'Asia/Jakarta');

    $result = $this->action->handle($this->room->id, $startAt, $endAt);

    expect($result['available'])->toBeFalse()
        ->and($result['reason'])->toContain('outside studio operating hours');
});

test('returns unavailable when outside operating hours (after close)', function () {
    $startAt = Carbon::parse('2026-10-10 21:00:00');
    $endAt = Carbon::parse('2026-10-10 23:00:00');

    $result = $this->action->handle($this->room->id, $startAt, $endAt);

    expect($result['available'])->toBeFalse()
        ->and($result['reason'])->toContain('outside studio operating hours');
});

test('returns available when within operating hours boundaries', function () {
    $startAt = Carbon::parse('2026-10-10 09:00:00');
    $endAt = Carbon::parse('2026-10-10 10:00:00');

    $result1 = $this->action->handle($this->room->id, $startAt, $endAt);
    expect($result1['available'])->toBeTrue();

    $startAt = Carbon::parse('2026-10-10 21:00:00', 'Asia/Jakarta');
    $endAt = Carbon::parse('2026-10-10 22:00:00', 'Asia/Jakarta');

    $result2 = $this->action->handle($this->room->id, $startAt, $endAt);
    expect($result2['available'])->toBeTrue();
});

test('handles closed days correctly', function () {
    // For testing closed days, we'll set Sunday hours very early (before typical booking times)
    // to simulate it being effectively closed for our test booking at 10:00
    // 2026-10-11 is a Sunday
    StudioSetting::updateSetting('operating_hours', [
        'monday' => '09:00-22:00',
        'tuesday' => '09:00-22:00',
        'wednesday' => '09:00-22:00',
        'thursday' => '09:00-22:00',
        'friday' => '09:00-22:00',
        'saturday' => '09:00-22:00',
        'sunday' => '00:01-00:02', // Effectively closed for normal hours
    ], $this->manager->id);

    $startAt = Carbon::parse('2026-10-11 10:00:00'); // Sunday
    $endAt = Carbon::parse('2026-10-11 12:00:00');

    $result = $this->action->handle($this->room->id, $startAt, $endAt);

    expect($result['available'])->toBeFalse()
        ->and($result['reason'])->toContain('outside studio operating hours');
});

test('validates multi-day bookings against each day operating hours', function () {
    // 2026-10-09 is Friday, 2026-10-10 is Saturday
    StudioSetting::updateSetting('operating_hours', [
        'monday' => '09:00-22:00',
        'tuesday' => '09:00-22:00',
        'wednesday' => '09:00-22:00',
        'thursday' => '09:00-22:00',
        'friday' => '09:00-23:59',
        'saturday' => '00:00-18:00',
        'sunday' => '09:00-22:00',
    ], $this->manager->id);

    $startAt = Carbon::parse('2026-10-09 21:00:00', 'Asia/Jakarta'); // Friday
    $endAt = Carbon::parse('2026-10-10 11:00:00', 'Asia/Jakarta');   // Saturday

    $result = $this->action->handle($this->room->id, $startAt, $endAt);

    expect($result['available'])->toBeFalse()
        ->and($result['reason'])->toContain('outside studio operating hours');
});

test('rejects multi-day booking when one day is outside hours', function () {
    // 2026-10-09 is Friday, 2026-10-10 is Saturday
    StudioSetting::updateSetting('operating_hours', [
        'monday' => '09:00-22:00',
        'tuesday' => '09:00-22:00',
        'wednesday' => '09:00-22:00',
        'thursday' => '09:00-22:00',
        'friday' => '09:00-22:00',
        'saturday' => '10:00-18:00',
        'sunday' => '09:00-22:00',
    ], $this->manager->id);

    $startAt = Carbon::parse('2026-10-09 21:00:00', 'Asia/Jakarta'); // Friday
    $endAt = Carbon::parse('2026-10-10 09:00:00', 'Asia/Jakarta');   // Saturday before 10:00

    $result = $this->action->handle($this->room->id, $startAt, $endAt);

    expect($result['available'])->toBeFalse()
        ->and($result['reason'])->toContain('outside studio operating hours');
});

test('throws exception when end_at equals start_at', function () {
    $startAt = Carbon::parse('2026-10-10 10:00:00');
    $endAt = Carbon::parse('2026-10-10 10:00:00');

    $this->action->handle($this->room->id, $startAt, $endAt);
})->throws(InvalidArgumentException::class, 'End time must be after start time');

test('throws exception when end_at is before start_at', function () {
    $startAt = Carbon::parse('2026-10-10 12:00:00');
    $endAt = Carbon::parse('2026-10-10 10:00:00');

    $this->action->handle($this->room->id, $startAt, $endAt);
})->throws(InvalidArgumentException::class, 'End time must be after start time');

test('throws exception when room does not exist', function () {
    $startAt = Carbon::parse('2026-10-10 10:00:00');
    $endAt = Carbon::parse('2026-10-10 12:00:00');

    $this->action->handle(9999, $startAt, $endAt);
})->throws(InvalidArgumentException::class, 'Room with ID 9999 not found');

test('different rooms do not conflict with each other', function () {
    $room2 = Room::factory()->create();

    RecordingSession::factory()
        ->for($room2)
        ->confirmed()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $startAt = Carbon::parse('2026-10-10 10:00:00');
    $endAt = Carbon::parse('2026-10-10 12:00:00');

    $result = $this->action->handle($this->room->id, $startAt, $endAt);

    expect($result['available'])->toBeTrue();
});
