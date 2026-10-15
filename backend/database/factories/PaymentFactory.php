<?php

namespace Database\Factories;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(500000, 5000000);

        return [
            'project_id' => Project::factory(),
            'invoice_id' => null,
            'type' => PaymentType::Po,
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
            'provider' => PaymentProvider::Midtrans,
            'order_id' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numberBetween(10000, 99999),
            'provider_transaction_id' => null,
            'payment_method' => null,
            'snap_token' => null,
            'payment_url' => null,
            'raw_payload' => null,
            'expires_at' => now()->addHours(24),
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Paid,
            'provider_transaction_id' => 'TXN-'.fake()->unique()->numerify('##########'),
            'payment_method' => fake()->randomElement(['bank_transfer', 'credit_card', 'gopay', 'shopeepay']),
            'paid_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Pending,
            'paid_at' => null,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'provider' => PaymentProvider::Cash,
            'snap_token' => null,
            'payment_url' => null,
        ]);
    }

    public function midtrans(): static
    {
        return $this->state(fn () => [
            'provider' => PaymentProvider::Midtrans,
            'snap_token' => 'fake-snap-token-'.fake()->uuid(),
            'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/'.fake()->uuid(),
        ]);
    }

    public function xendit(): static
    {
        return $this->state(fn () => [
            'provider' => PaymentProvider::Xendit,
            'payment_url' => 'https://checkout.xendit.co/web/'.fake()->uuid(),
        ]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn () => [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total,
        ]);
    }
}
