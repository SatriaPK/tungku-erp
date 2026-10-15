<?php

namespace App\Actions\Booking;

use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\StudioSetting;
use Carbon\Carbon;
use InvalidArgumentException;

class CheckAvailability
{
    /**
     * Check if a time slot is available for booking.
     *
     * @return array{available: bool, reason: ?string}
     *
     * @throws InvalidArgumentException
     */
    public function handle(int $roomId, Carbon $startAt, Carbon $endAt): array
    {
        // Validate room exists
        if (! Room::where('id', $roomId)->exists()) {
            throw new InvalidArgumentException("Room with ID {$roomId} not found.");
        }

        // Validate end_at > start_at
        if ($endAt->lessThanOrEqualTo($startAt)) {
            throw new InvalidArgumentException('End time must be after start time.');
        }

        $startAt = $startAt->copy()->setTimezone(config('app.timezone'));
        $endAt = $endAt->copy()->setTimezone(config('app.timezone'));

        // Check for blocking sessions (confirmed or active holds)
        $hasConflict = RecordingSession::where('room_id', $roomId)
            ->blocking()
            ->overlapping($startAt, $endAt)
            ->exists();

        if ($hasConflict) {
            return [
                'available' => false,
                'reason' => 'Time slot conflicts with an existing booking or active hold.',
            ];
        }

        // Check operating hours
        $operatingHours = StudioSetting::getSetting('operating_hours');

        if (! $this->isWithinOperatingHours($startAt, $endAt, $operatingHours)) {
            return [
                'available' => false,
                'reason' => 'Time slot is outside studio operating hours.',
            ];
        }

        return [
            'available' => true,
            'reason' => null,
        ];
    }

    /**
     * Check if a time range falls within operating hours.
     *
     * Validates that the start time and end time fall within operating hours
     * for their respective days. This allows bookings to span multiple days
     * including periods when the studio would otherwise be closed.
     *
     * @param  array<string, string>  $operatingHours
     */
    private function isWithinOperatingHours(Carbon $startAt, Carbon $endAt, array $operatingHours): bool
    {
        for ($day = $startAt->copy()->startOfDay(); $day->lessThan($endAt); $day->addDay()) {
            $dayName = strtolower($day->format('l'));
            $dayHours = $operatingHours[$dayName] ?? null;

            if ($dayHours === null) {
                return false;
            }

            [$open, $close] = explode('-', $dayHours);
            $openAt = Carbon::parse($day->format('Y-m-d').' '.$open, config('app.timezone'));
            $closeAt = Carbon::parse($day->format('Y-m-d').' '.$close, config('app.timezone'));
            $intervalStart = $startAt->greaterThan($day) ? $startAt : $day;
            $nextDay = $day->copy()->addDay();
            $intervalEnd = $endAt->lessThan($nextDay) ? $endAt : $nextDay;

            if ($intervalStart->equalTo($intervalEnd)) {
                continue;
            }

            if ($intervalStart->lessThan($openAt) || $intervalEnd->greaterThan($closeAt)) {
                return false;
            }
        }

        return true;
    }
}
