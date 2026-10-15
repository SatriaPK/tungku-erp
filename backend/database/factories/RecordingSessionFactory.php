<?php

namespace Database\Factories;

use App\Enum\SessionStatus;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordingSession>
 */
class RecordingSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 30))->setTime(10, 0);
        $end = $start->copy()->addHours(3);

        return [
            'room_id' => Room::factory(),
            'project_id' => null,
            'project_stage_id' => null,
            'client_id' => User::factory(),
            'start_at' => $start,
            'end_at' => $end,
            'status' => SessionStatus::Held,
            'held_until' => now()->addMinutes(15),
            'is_extension' => false,
            'extra_fee' => 0,
            'requested_by' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => SessionStatus::Confirmed,
            'held_until' => null,
        ]);
    }

    public function held(?int $minutesUntilExpiry = 15): static
    {
        return $this->state(fn () => [
            'status' => SessionStatus::Held,
            'held_until' => now()->addMinutes($minutesUntilExpiry),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SessionStatus::Expired,
            'held_until' => now()->subMinutes(10),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => SessionStatus::Cancelled,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => SessionStatus::Completed,
        ]);
    }

    public function timeRange($start, $end): static
    {
        return $this->state(fn () => [
            'start_at' => $start,
            'end_at' => $end,
        ]);
    }
}
