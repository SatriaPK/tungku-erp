<?php

use App\Actions\Booking\ConfirmSlot;
use App\Enum\SessionStatus;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\StudioSettingSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->seed(StudioSettingSeeder::class);
    $this->action = app(ConfirmSlot::class);
    $this->room = Room::factory()->create();
    $this->client = User::factory()->create();
});

test('successfully confirms held session', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->held(60)
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $result = $this->action->handle($session->id);

    expect($result)->toBeInstanceOf(RecordingSession::class)
        ->and($result->id)->toBe($session->id)
        ->and($result->status)->toBe(SessionStatus::Confirmed)
        ->and($result->held_until)->toBeNull();
});

test('clears held_until timestamp after confirmation', function () {
    Carbon::setTestNow('2026-10-08 16:00:00');

    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->held(60)
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    expect($session->held_until)->not->toBeNull();

    $result = $this->action->handle($session->id);

    expect($result->held_until)->toBeNull();

    Carbon::setTestNow();
});

test('does not modify other session fields', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->held(60)
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $originalRoomId = $session->room_id;
    $originalClientId = $session->client_id;
    $originalStartAt = $session->start_at->toDateTimeString();
    $originalEndAt = $session->end_at->toDateTimeString();
    $originalProjectId = $session->project_id;
    $originalProjectStageId = $session->project_stage_id;

    $result = $this->action->handle($session->id);

    expect($result->room_id)->toBe($originalRoomId)
        ->and($result->client_id)->toBe($originalClientId)
        ->and($result->start_at->toDateTimeString())->toBe($originalStartAt)
        ->and($result->end_at->toDateTimeString())->toBe($originalEndAt)
        ->and($result->project_id)->toBe($originalProjectId)
        ->and($result->project_stage_id)->toBe($originalProjectStageId);
});

test('throws exception when session not found', function () {
    $this->action->handle(9999);
})->throws(ModelNotFoundException::class, 'Recording session with ID 9999 not found');

test('throws exception when session is already confirmed', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->confirmed()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $this->action->handle($session->id);
})->throws(InvalidArgumentException::class, "Current status is 'confirmed', expected 'held'");

test('throws exception when session is completed', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->completed()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $this->action->handle($session->id);
})->throws(InvalidArgumentException::class, "Current status is 'completed', expected 'held'");

test('throws exception when session is cancelled', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->cancelled()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $this->action->handle($session->id);
})->throws(InvalidArgumentException::class, "Current status is 'cancelled', expected 'held'");

test('throws exception when hold has expired', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->held(-60) // Expired 60 minutes ago
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $this->action->handle($session->id);
})->throws(InvalidArgumentException::class, 'The hold has expired');

test('throws exception when held_until is null', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create([
            'status' => SessionStatus::Held,
            'held_until' => null,
        ]);

    $this->action->handle($session->id);
})->throws(InvalidArgumentException::class, 'The hold has expired');

test('confirms session just before hold expiration', function () {
    Carbon::setTestNow('2026-10-08 16:00:00');

    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create([
            'status' => SessionStatus::Held,
            'held_until' => Carbon::parse('2026-10-08 16:00:01'), // 1 second from now
        ]);

    $result = $this->action->handle($session->id);

    expect($result->status)->toBe(SessionStatus::Confirmed);

    Carbon::setTestNow();
});

test('persists confirmation to database', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->held(60)
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $this->action->handle($session->id);

    $refreshed = RecordingSession::find($session->id);

    expect($refreshed->status)->toBe(SessionStatus::Confirmed)
        ->and($refreshed->held_until)->toBeNull();
});
