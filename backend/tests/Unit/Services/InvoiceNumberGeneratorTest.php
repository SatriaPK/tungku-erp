<?php

use App\Models\Invoice;
use App\Services\InvoiceNumberGenerator;

beforeEach(function () {
    $this->generator = new InvoiceNumberGenerator;
});

test('generates first invoice number for year', function () {
    $number = $this->generator->generate(2026);
    expect($number)->toBe('INV-2026-00001');
});

test('generates sequential invoice numbers', function () {
    Invoice::factory()->create(['invoice_number' => 'INV-2026-00001']);
    Invoice::factory()->create(['invoice_number' => 'INV-2026-00002']);

    $number = $this->generator->generate(2026);
    expect($number)->toBe('INV-2026-00003');
});

test('handles year rollover', function () {
    Invoice::factory()->create(['invoice_number' => 'INV-2025-00999']);
    Invoice::factory()->create(['invoice_number' => 'INV-2026-00001']);

    $number2026 = $this->generator->generate(2026);
    $number2027 = $this->generator->generate(2027);

    expect($number2026)->toBe('INV-2026-00002');
    expect($number2027)->toBe('INV-2027-00001');
});

test('validates invoice number format', function () {
    expect($this->generator->isValid('INV-2026-00001'))->toBeTrue();
    expect($this->generator->isValid('INV-2026-99999'))->toBeTrue();
    expect($this->generator->isValid('INV-2026-0001'))->toBeFalse();
    expect($this->generator->isValid('INV-26-00001'))->toBeFalse();
});

test('extracts year from invoice number', function () {
    expect($this->generator->extractYear('INV-2026-00001'))->toBe(2026);
    expect($this->generator->extractYear('INVALID'))->toBeNull();
});

test('extracts sequence from invoice number', function () {
    expect($this->generator->extractSequence('INV-2026-00001'))->toBe(1);
    expect($this->generator->extractSequence('INV-2026-00123'))->toBe(123);
    expect($this->generator->extractSequence('INVALID'))->toBeNull();
});
