<?php

namespace Database\Factories;

use App\Enum\OperatorActivityType;
use App\Enum\StageType;
use App\Models\OperatorActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatorActivity>
 */
class OperatorActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => OperatorActivityType::DeadlineConfirmed,
            'source_id' => fake()->unique()->numberBetween(1, 1000000000),
            'stage' => StageType::Editing,
            'project_name' => 'Project fixture',
            'deadline_at' => now()->addDay(),
            'occurred_at' => now(),
            'recorded_at' => now(),
        ];
    }
}
