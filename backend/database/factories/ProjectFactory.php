<?php

namespace Database\Factories;

use App\Enum\ProjectStatus;
use App\Models\Package;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PRJ-'.fake()->unique()->numerify('####-###'),
            'client_id' => User::factory()->create(['role' => 'client']),
            'package_id' => Package::factory(),
            'name' => fake()->words(3, true),
            'package_snapshot' => [],
            'price' => fake()->numberBetween(5000000, 50000000),
            'po_amount' => fake()->numberBetween(5000000, 50000000),
            'status' => ProjectStatus::PendingPayment,
        ];
    }
}
