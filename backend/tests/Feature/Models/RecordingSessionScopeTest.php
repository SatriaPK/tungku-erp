<?php

use App\Enum\SessionStatus;
use App\Models\RecordingSession;
use App\Models\Room;

beforeEach(function () {
    $this->room = Room::factory()->create();
});

it('blocking includes confirmed sessions', function () {
    RecordingSession::factory()->for($this->room)->confirmed()->create();
    expect(RecordingSession::blocking()->get())->toHaveCount(1);
});

it('blocking includes held sessions with future held_until', function () {
    RecordingSession::factory()->for($this->room)->held(minutesUntilExpiry: 30)->create();
    expect(RecordingSession::blocking()->get())->toHaveCount(1);
});

it('blocking excludes expired held sessions', function () {
    RecordingSession::factory()->for($this->room)
        ->state(['status' => SessionStatus::Held, 'held_until' => now()->subMinutes(10)])
        ->create();
    expect(RecordingSession::blocking()->get())->toHaveCount(0);
});

it('blocking excludes cancelled sessions', function () {
    RecordingSession::factory()->for($this->room)->cancelled()->create();
    expect(RecordingSession::blocking()->get())->toHaveCount(0);
});

it('overlapping detects overlap', function () {
    $start = now()->setTime(10, 0);
    $end = $start->copy()->addHours(2);
    RecordingSession::factory()->for($this->room)->timeRange($start, $end)->create();
    expect(RecordingSession::overlapping($start, $end)->get())->toHaveCount(1);
});

it('overlapping excludes non-overlapping sessions', function () {
    $start = now()->setTime(9, 0);
    RecordingSession::factory()->for($this->room)
        ->timeRange($start, $start->copy()->addHours(1))
        ->create();
    $query = now()->setTime(11, 0);
    expect(RecordingSession::overlapping($query, $query->copy()->addHours(1))->get())->toHaveCount(0);
});

it('overlapping excludes adjacent sessions', function () {
    $start = now()->setTime(9, 0);
    RecordingSession::factory()->for($this->room)
        ->timeRange($start, $start->copy()->addHours(1))
        ->create();
    $query = now()->setTime(10, 0);
    expect(RecordingSession::overlapping($query, $query->copy()->addHours(1))->get())->toHaveCount(0);
});

it('combined blocking and overlapping filters correctly', function () {
    $start = now()->setTime(10, 0);
    $end = $start->copy()->addHours(2);

    RecordingSession::factory()->for($this->room)->confirmed()->timeRange($start, $end)->create();
    RecordingSession::factory()->for($this->room)->held(30)->timeRange($start, $end)->create();
    RecordingSession::factory()->for($this->room)
        ->state(['status' => SessionStatus::Held, 'held_until' => now()->subMinutes(10)])
        ->timeRange($start, $end)->create();

    expect(RecordingSession::blocking()->overlapping($start, $end)->get())->toHaveCount(2);
});
