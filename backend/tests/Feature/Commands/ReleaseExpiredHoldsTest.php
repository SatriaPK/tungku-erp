<?php

use App\Enum\SessionStatus;
use App\Models\RecordingSession;
use Illuminate\Support\Carbon;

it('releases expired held sessions and marks them as expired', function () {
    Carbon::setTestNow('2026-10-08 16:00:00');
    $expired = RecordingSession::factory()->held(-10)->create();

    $this->artisan('bookings:release-expired-holds')
        ->expectsOutput('Released 1 expired hold(s).')
        ->assertExitCode(0);

    $expired->refresh();

    expect($expired->status)->toBe(SessionStatus::Expired)
        ->and($expired->held_until)->toBeNull();

    Carbon::setTestNow();
});

it('does not release active or non-held sessions', function () {
    Carbon::setTestNow('2026-10-08 16:00:00');
    $active = RecordingSession::factory()->held(10)->create();
    $confirmed = RecordingSession::factory()->confirmed()->create();
    $cancelled = RecordingSession::factory()->cancelled()->create();

    $this->artisan('bookings:release-expired-holds')
        ->expectsOutput('Released 0 expired hold(s).')
        ->assertExitCode(0);

    expect($active->fresh()->status)->toBe(SessionStatus::Held)
        ->and($confirmed->fresh()->status)->toBe(SessionStatus::Confirmed)
        ->and($cancelled->fresh()->status)->toBe(SessionStatus::Cancelled);

    Carbon::setTestNow();
});

it('releases multiple expired holds in one run', function () {
    Carbon::setTestNow('2026-10-08 16:00:00');
    $sessions = RecordingSession::factory()->count(3)->held(-1)->create();

    $this->artisan('bookings:release-expired-holds')
        ->expectsOutput('Released 3 expired hold(s).')
        ->assertSuccessful();

    $sessions->each(function (RecordingSession $session): void {
        expect($session->fresh()->status)->toBe(SessionStatus::Expired)
            ->and($session->fresh()->held_until)->toBeNull();
    });

    Carbon::setTestNow();
});

it('registers the expired hold command with the scheduler', function () {
    $this->artisan('schedule:list')->assertSuccessful();
});
