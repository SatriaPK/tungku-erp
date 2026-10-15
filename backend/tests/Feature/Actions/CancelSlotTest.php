<?php

use App\Actions\Booking\CancelSlot;
use App\Enum\SessionStatus;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\StudioSettingSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->seed(StudioSettingSeeder::class);
    $this->action = app(CancelSlot::class);
    $this->room = Room::factory()->create();
    $this->client = User::factory()->create();
});

test('successfully cancels held session', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->held(60)
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $result = $this->action->handle($session->id);

    expect($result)->toBeInstanceOf(RecordingSession::class)
        ->and($result->id)->toBe($session->id)
        ->and($result->status)->toBe(SessionStatus::Cancelled)
        ->and($result->held_until)->toBeNull();
});

test('successfully cancels confirmed session', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->confirmed()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $result = $this->action->handle($session->id);

    expect($result)->toBeInstanceOf(RecordingSession::class)
        ->and($result->id)->toBe($session->id)
        ->and($result->status)->toBe(SessionStatus::Cancelled);
});

test('clears held_until when cancelling held session', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->held(60)
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    expect($session->held_until)->not->toBeNull();

    $result = $this->action->handle($session->id);

    expect($result->held_until)->toBeNull();
});

test('does not modify other session fields', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->confirmed()
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

test('throws exception when session is already cancelled', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->cancelled()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $this->action->handle($session->id);
})->throws(InvalidArgumentException::class, 'already cancelled');

test('throws exception when session is completed', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->completed()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $this->action->handle($session->id);
})->throws(InvalidArgumentException::class, 'Completed sessions cannot be cancelled');

test('persists cancellation to database', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->confirmed()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $this->action->handle($session->id);

    $refreshed = RecordingSession::find($session->id);

    expect($refreshed->status)->toBe(SessionStatus::Cancelled)
        ->and($refreshed->held_until)->toBeNull();
});

test('accepts optional reason parameter without storing it', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->confirmed()
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $result = $this->action->handle($session->id, 'Client requested cancellation');

    expect($result->status)->toBe(SessionStatus::Cancelled);
});

test('can cancel expired hold', function () {
    $session = RecordingSession::factory()
        ->for($this->room)
        ->for($this->client, 'client')
        ->held(-60) // Expired 60 minutes ago
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $result = $this->action->handle($session->id);

    expect($result->status)->toBe(SessionStatus::Cancelled);
});
