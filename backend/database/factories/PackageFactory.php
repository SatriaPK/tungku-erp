<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'track_count' => fake()->numberBetween(1, 10),
            'recording_hours' => fake()->numberBetween(5, 20),
            'include_recording' => true,
            'include_editing' => true,
            'include_mixing' => true,
            'include_mastering' => true,
            'auto_price' => fake()->numberBetween(3000000, 10000000),
            'manual_price' => null,
            'is_price_manual' => false,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function manualPrice(int $price): static
    {
        return $this->state(fn () => [
            'manual_price' => $price,
            'is_price_manual' => true,
        ]);
    }
}
