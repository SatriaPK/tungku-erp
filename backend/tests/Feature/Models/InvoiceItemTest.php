<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use App\Models\RecordingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('invoice item can be created with basic attributes', function () {
    $invoice = Invoice::factory()->create();

    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
    ]);

    expect($item->invoice_id)->toBe($invoice->id)
        ->and($item->description)->not->toBeEmpty()
        ->and($item->unit_price)->toBeGreaterThan(0)
        ->and($item->quantity)->toBeGreaterThan(0)
        ->and($item->total)->toBeGreaterThan(0);
});

test('invoice item belongs to invoice', function () {
    $invoice = Invoice::factory()->create();
    $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

    expect($item->invoice)->toBeInstanceOf(Invoice::class)
        ->and($item->invoice->id)->toBe($invoice->id);
});

test('invoice item can reference package', function () {
    $package = Package::factory()->create();
    $item = InvoiceItem::factory()->forPackage($package)->create([
        'invoice_id' => Invoice::factory()->create()->id,
    ]);

    expect($item->package)->toBeInstanceOf(Package::class)
        ->and($item->package_id)->toBe($package->id);
});

test('invoice item can reference recording session', function () {
    $session = RecordingSession::factory()->create();
    $item = InvoiceItem::factory()->forSession($session)->create([
        'invoice_id' => Invoice::factory()->create()->id,
    ]);

    expect($item->recordingSession)->toBeInstanceOf(RecordingSession::class)
        ->and($item->recording_session_id)->toBe($session->id);
});

test('calculate amounts returns correct totals without tax or discount', function () {
    $amounts = InvoiceItem::calculateAmounts(
        unitPrice: 100000,
        quantity: 2,
        taxRate: 0,
        discountAmount: 0
    );

    expect($amounts['subtotal'])->toBe(200000)
        ->and($amounts['tax_amount'])->toBe(0)
        ->and($amounts['discount_amount'])->toBe(0)
        ->and($amounts['total'])->toBe(200000);
});

test('calculate amounts applies tax correctly', function () {
    $amounts = InvoiceItem::calculateAmounts(
        unitPrice: 100000,
        quantity: 1,
        taxRate: 11,
        discountAmount: 0
    );

    expect($amounts['subtotal'])->toBe(100000)
        ->and($amounts['tax_amount'])->toBe(11000)
        ->and($amounts['discount_amount'])->toBe(0)
        ->and($amounts['total'])->toBe(111000);
});

test('calculate amounts applies discount correctly', function () {
    $amounts = InvoiceItem::calculateAmounts(
        unitPrice: 100000,
        quantity: 2,
        taxRate: 0,
        discountAmount: 20000
    );

    expect($amounts['subtotal'])->toBe(200000)
        ->and($amounts['tax_amount'])->toBe(0)
        ->and($amounts['discount_amount'])->toBe(20000)
        ->and($amounts['total'])->toBe(180000);
});

test('calculate amounts with tax and discount', function () {
    $amounts = InvoiceItem::calculateAmounts(
        unitPrice: 100000,
        quantity: 3,
        taxRate: 11,
        discountAmount: 30000
    );

    expect($amounts['subtotal'])->toBe(300000)
        ->and($amounts['tax_amount'])->toBe(33000)
        ->and($amounts['discount_amount'])->toBe(30000)
        ->and($amounts['total'])->toBe(303000);
});

test('calculate amounts prevents negative total', function () {
    $amounts = InvoiceItem::calculateAmounts(
        unitPrice: 100000,
        quantity: 1,
        taxRate: 0,
        discountAmount: 150000
    );

    expect($amounts['total'])->toBe(0);
});

test('invoice item factory for package calculates correctly', function () {
    $package = Package::factory()->create(['manual_price' => 500000, 'is_price_manual' => true]);
    $invoice = Invoice::factory()->create();

    $item = InvoiceItem::factory()->forPackage($package)->create([
        'invoice_id' => $invoice->id,
    ]);

    expect($item->description)->toContain($package->name)
        ->and($item->unit_price)->toBe($package->price)
        ->and($item->quantity)->toBe(1)
        ->and($item->package_id)->toBe($package->id);
});

test('invoice item factory for session calculates correctly', function () {
    $session = RecordingSession::factory()->create([
        'extra_fee' => 750000,
        'start_at' => now()->addDays(2),
    ]);
    $invoice = Invoice::factory()->create();

    $item = InvoiceItem::factory()->forSession($session)->create([
        'invoice_id' => $invoice->id,
    ]);

    expect($item->description)->toContain('Sesi Recording')
        ->and($item->unit_price)->toBe(750000)
        ->and($item->quantity)->toBe(1)
        ->and($item->recording_session_id)->toBe($session->id);
});

test('invoice item casts amounts to integers', function () {
    $item = InvoiceItem::factory()->create();

    expect($item->unit_price)->toBeInt()
        ->and($item->quantity)->toBeInt()
        ->and($item->subtotal)->toBeInt()
        ->and($item->tax_amount)->toBeInt()
        ->and($item->discount_amount)->toBeInt()
        ->and($item->total)->toBeInt();
});

test('multiple items can belong to same invoice', function () {
    $invoice = Invoice::factory()->create();

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_number' => 1]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_number' => 2]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_number' => 3]);

    // Reload relation fresh from DB
    expect($invoice->items()->count())->toBe(3);
});
