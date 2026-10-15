<?php

use App\Actions\Booking\HoldSlot;
use App\Enum\ProjectStatus;
use App\Enum\SessionStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\StudioSetting;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\StudioSettingSeeder;

beforeEach(function () {
    $this->seed(StudioSettingSeeder::class);
    $this->action = app(HoldSlot::class);
    $this->room = Room::factory()->create();
    $this->client = User::factory()->create(); // Default role is Client
});

test('successfully holds available slot', function () {
    $startAt = Carbon::parse('2026-10-10 10:00:00', 'Asia/Jakarta');
    $endAt = Carbon::parse('2026-10-10 12:00:00', 'Asia/Jakarta');

    $session = $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt
    );

    expect($session)->toBeInstanceOf(RecordingSession::class)
        ->and($session->room_id)->toBe($this->room->id)
        ->and($session->client_id)->toBe($this->client->id)
        ->and($session->start_at->toDateTimeString())->toBe('2026-10-10 10:00:00')
        ->and($session->end_at->toDateTimeString())->toBe('2026-10-10 12:00:00')
        ->and($session->status)->toBe(SessionStatus::Held)
        ->and($session->is_extension)->toBeFalse()
        ->and($session->extra_fee)->toBe(0)
        ->and($session->requested_by)->toBeNull();
});

test('sets held_until correctly using hold_duration_minutes from settings', function () {
    Carbon::setTestNow('2026-10-08 16:00:00');

    $startAt = Carbon::parse('2026-10-10 10:00:00', 'Asia/Jakarta');
    $endAt = Carbon::parse('2026-10-10 12:00:00', 'Asia/Jakarta');

    $session = $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt
    );

    // Default hold_duration_minutes is 15
    $expectedHeldUntil = Carbon::parse('2026-10-08 16:15:00');

    expect($session->held_until->toDateTimeString())->toBe($expectedHeldUntil->toDateTimeString());

    Carbon::setTestNow();
});

test('throws exception when slot conflicts with confirmed session', function () {
    RecordingSession::factory()
        ->for($this->room)
        ->confirmed()
        ->timeRange(Carbon::parse('2026-10-10 10:00:00', 'Asia/Jakarta'), Carbon::parse('2026-10-10 12:00:00', 'Asia/Jakarta'))
        ->create();

    $startAt = Carbon::parse('2026-10-10 11:00:00', 'Asia/Jakarta');
    $endAt = Carbon::parse('2026-10-10 13:00:00', 'Asia/Jakarta');

    $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt
    );
})->throws(RuntimeException::class, 'Slot is not available');

test('throws exception when slot conflicts with active hold', function () {
    RecordingSession::factory()
        ->for($this->room)
        ->held(60)
        ->timeRange(Carbon::parse('2026-10-10 10:00:00', 'Asia/Jakarta'), Carbon::parse('2026-10-10 12:00:00', 'Asia/Jakarta'))
        ->create();

    $startAt = Carbon::parse('2026-10-10 11:00:00', 'Asia/Jakarta');
    $endAt = Carbon::parse('2026-10-10 13:00:00', 'Asia/Jakarta');

    $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt
    );
})->throws(RuntimeException::class, 'Slot is not available');

test('throws exception when outside operating hours', function () {
    $startAt = Carbon::parse('2026-10-10 08:00:00', 'Asia/Jakarta');
    $endAt = Carbon::parse('2026-10-10 10:00:00', 'Asia/Jakarta');

    $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt
    );
})->throws(RuntimeException::class, 'outside studio operating hours');

test('throws exception when end_at equals start_at', function () {
    $startAt = Carbon::parse('2026-10-10 10:00:00');
    $endAt = Carbon::parse('2026-10-10 10:00:00');

    $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt
    );
})->throws(InvalidArgumentException::class, 'End time must be after start time');

test('throws exception when end_at is before start_at', function () {
    $startAt = Carbon::parse('2026-10-10 12:00:00');
    $endAt = Carbon::parse('2026-10-10 10:00:00');

    $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt
    );
})->throws(InvalidArgumentException::class, 'End time must be after start time');

test('throws exception when room does not exist', function () {
    $startAt = Carbon::parse('2026-10-10 10:00:00');
    $endAt = Carbon::parse('2026-10-10 12:00:00');

    $this->action->handle(
        9999,
        $this->client->id,
        $startAt,
        $endAt
    );
})->throws(InvalidArgumentException::class, 'Room with ID 9999 not found');

test('throws exception when client does not exist', function () {
    $startAt = Carbon::parse('2026-10-10 10:00:00');
    $endAt = Carbon::parse('2026-10-10 12:00:00');

    $this->action->handle(
        $this->room->id,
        9999,
        $startAt,
        $endAt
    );
})->throws(InvalidArgumentException::class, 'Client with ID 9999 not found');

test('allows holding slot after expired hold expires', function () {
    RecordingSession::factory()
        ->for($this->room)
        ->held(-60)
        ->timeRange('2026-10-10 10:00:00', '2026-10-10 12:00:00')
        ->create();

    $startAt = Carbon::parse('2026-10-10 10:00:00');
    $endAt = Carbon::parse('2026-10-10 12:00:00');

    $session = $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt
    );

    expect($session)->toBeInstanceOf(RecordingSession::class)
        ->and($session->status)->toBe(SessionStatus::Held);
});

test('associates with project_id when provided', function () {
    $project = Project::create([
        'code' => 'TEST-001',
        'client_id' => $this->client->id,
        'name' => 'Test Project',
        'price' => 1000000,
        'po_amount' => 500000,
        'status' => ProjectStatus::AwaitingAssignment,
        'package_snapshot' => [],
    ]);

    $startAt = Carbon::parse('2026-10-10 10:00:00');
    $endAt = Carbon::parse('2026-10-10 12:00:00');

    $session = $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt,
        projectId: $project->id
    );

    expect($session->project_id)->toBe($project->id);
});

test('associates with project_stage_id when provided', function () {
    $project = Project::create([
        'code' => 'TEST-002',
        'client_id' => $this->client->id,
        'name' => 'Test Project 2',
        'price' => 1000000,
        'po_amount' => 500000,
        'status' => ProjectStatus::InProgress,
        'package_snapshot' => [],
    ]);

    $stage = ProjectStage::create([
        'project_id' => $project->id,
        'stage' => StageType::Recording,
        'sequence' => 1,
        'status' => StageStatus::InProgress,
    ]);

    $startAt = Carbon::parse('2026-10-10 10:00:00');
    $endAt = Carbon::parse('2026-10-10 12:00:00');

    $session = $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt,
        projectId: $project->id,
        projectStageId: $stage->id
    );

    expect($session->project_id)->toBe($project->id)
        ->and($session->project_stage_id)->toBe($stage->id);
});

test('does not require project_id or project_stage_id', function () {
    $startAt = Carbon::parse('2026-10-10 10:00:00');
    $endAt = Carbon::parse('2026-10-10 12:00:00');

    $session = $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt
    );

    expect($session->project_id)->toBeNull()
        ->and($session->project_stage_id)->toBeNull();
});

test('creates session with correct default values', function () {
    $startAt = Carbon::parse('2026-10-10 10:00:00');
    $endAt = Carbon::parse('2026-10-10 12:00:00');

    $session = $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt
    );

    expect($session->is_extension)->toBeFalse()
        ->and($session->extra_fee)->toBe(0)
        ->and($session->requested_by)->toBeNull();
});

test('respects updated hold_duration_minutes setting', function () {
    Carbon::setTestNow('2026-10-08 16:00:00');

    $manager = User::factory()->manager()->create();
    StudioSetting::updateSetting('hold_duration_minutes', 20, $manager->id);

    $startAt = Carbon::parse('2026-10-10 10:00:00');
    $endAt = Carbon::parse('2026-10-10 12:00:00');

    $session = $this->action->handle(
        $this->room->id,
        $this->client->id,
        $startAt,
        $endAt
    );

    $expectedHeldUntil = Carbon::parse('2026-10-08 16:20:00');

    expect($session->held_until->toDateTimeString())->toBe($expectedHeldUntil->toDateTimeString());

    Carbon::setTestNow();
});
