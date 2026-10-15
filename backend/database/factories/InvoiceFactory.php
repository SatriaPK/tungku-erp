<?php

namespace Database\Factories;

use App\Enum\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\RecordingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(100000, 5000000);
        $taxAmount = (int) round($subtotal * 0.11); // PPN 11%
        $discountAmount = 0;
        $total = $subtotal + $taxAmount - $discountAmount;

        return [
            'invoice_number' => 'INV-'.now()->format('Y').'-'.fake()->unique()->numberBetween(1000, 9999),
            'client_id' => User::factory()->client(),
            'project_id' => null,
            'recording_session_id' => null,
            'status' => InvoiceStatus::Draft,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'notes' => fake()->optional()->sentence(),
            'metadata' => null,
            'issue_date' => null,
            'due_date' => null,
            'paid_at' => null,
            'created_by' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Issued,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Paid,
            'issue_date' => now()->subDays(5)->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'paid_at' => now()->subDay(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Cancelled,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Issued,
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(3)->toDateString(),
        ]);
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn () => [
            'client_id' => $project->client_id,
            'project_id' => $project->id,
        ]);
    }

    public function forSession(RecordingSession $session): static
    {
        return $this->state(fn () => [
            'client_id' => $session->client_id,
            'recording_session_id' => $session->id,
        ]);
    }
}
