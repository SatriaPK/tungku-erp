<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use App\Models\RecordingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(50000, 2000000);
        $quantity = fake()->numberBetween(1, 5);
        $subtotal = $unitPrice * $quantity;
        $taxAmount = (int) round($subtotal * 0.11);
        $discountAmount = 0;
        $total = $subtotal + $taxAmount - $discountAmount;

        return [
            'invoice_id' => Invoice::factory(),
            'line_number' => 1,
            'description' => fake()->sentence(3),
            'details' => fake()->optional()->paragraph(),
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'package_id' => null,
            'recording_session_id' => null,
        ];
    }

    public function forPackage(Package $package): static
    {
        return $this->state(fn () => [
            'description' => 'Paket '.$package->name,
            'unit_price' => $package->price,
            'quantity' => 1,
            'subtotal' => $package->price,
            'tax_amount' => (int) round($package->price * 0.11),
            'discount_amount' => 0,
            'total' => $package->price + (int) round($package->price * 0.11),
            'package_id' => $package->id,
        ]);
    }

    public function forSession(RecordingSession $session): static
    {
        $fee = $session->extra_fee ?: 500000;

        return $this->state(fn () => [
            'description' => 'Sesi Recording '.($session->start_at?->format('d M Y') ?? ''),
            'unit_price' => $fee,
            'quantity' => 1,
            'subtotal' => $fee,
            'tax_amount' => (int) round($fee * 0.11),
            'discount_amount' => 0,
            'total' => $fee + (int) round($fee * 0.11),
            'recording_session_id' => $session->id,
        ]);
    }
}
