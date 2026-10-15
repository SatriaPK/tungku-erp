<?php

use App\Actions\Invoice\IssueInvoice;
use App\Enum\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;

beforeEach(function () {
    $this->action = new IssueInvoice;
});

test('issues draft invoice', function () {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Draft,
        'issue_date' => null,
    ]);
    // Must have at least one item and total > 0
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

    $issued = $this->action->execute($invoice);

    expect($issued->status)->toBe(InvoiceStatus::Issued);
    expect($issued->issue_date)->not->toBeNull();
});

test('throws exception if not draft status', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Issued]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);
    $this->action->execute($invoice);
})->throws(InvalidArgumentException::class, 'Invoice must be in Draft status');

test('throws exception if no items', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);
    // Ensure no items exist
    $invoice->items()->delete();
    $this->action->execute($invoice);
})->throws(InvalidArgumentException::class, 'Invoice must have at least one item');

test('throws exception if total is zero', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft, 'total' => 0]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 0,
    ]);
    $this->action->execute($invoice);
})->throws(InvalidArgumentException::class, 'Invoice total must be greater than zero');

test('throws exception if no client', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

    // Manually set client_id to null after creation to bypass DB constraints
    $invoice->client_id = null;

    $this->action->execute($invoice);
})->throws(InvalidArgumentException::class, 'Invoice must be associated with a client');

test('loads relationships in returned invoice', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

    $issued = $this->action->execute($invoice);

    expect($issued->relationLoaded('items'))->toBeTrue();
    expect($issued->relationLoaded('client'))->toBeTrue();
    expect($issued->relationLoaded('project'))->toBeTrue();
});
