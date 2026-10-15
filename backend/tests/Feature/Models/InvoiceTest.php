<?php

use App\Enum\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\RecordingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('invoice can be created with basic attributes', function () {
    $client = User::factory()->client()->create();

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
    ]);

    expect($invoice->invoice_number)->toStartWith('INV-')
        ->and($invoice->client_id)->toBe($client->id)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->total)->toBeGreaterThan(0);
});

test('invoice belongs to client', function () {
    $client = User::factory()->client()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);

    expect($invoice->client)->toBeInstanceOf(User::class)
        ->and($invoice->client->id)->toBe($client->id);
});

test('invoice can belong to project', function () {
    $project = Project::factory()->create();
    $invoice = Invoice::factory()->forProject($project)->create();

    expect($invoice->project)->toBeInstanceOf(Project::class)
        ->and($invoice->project_id)->toBe($project->id)
        ->and($invoice->client_id)->toBe($project->client_id);
});

test('invoice can belong to recording session', function () {
    $session = RecordingSession::factory()->create();
    $invoice = Invoice::factory()->forSession($session)->create();

    expect($invoice->recordingSession)->toBeInstanceOf(RecordingSession::class)
        ->and($invoice->recording_session_id)->toBe($session->id)
        ->and($invoice->client_id)->toBe($session->client_id);
});

test('invoice has many items', function () {
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->count(3)->create([
        'invoice_id' => $invoice->id,
    ]);

    // Invoice::factory() does NOT auto-create items, so we have exactly 3
    expect($invoice->items)->toHaveCount(3)
        ->and($invoice->items->first())->toBeInstanceOf(InvoiceItem::class);
});

test('invoice items are ordered by line number', function () {
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_number' => 3]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_number' => 1]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_number' => 2]);

    $lineNumbers = $invoice->items->pluck('line_number')->toArray();
    expect($lineNumbers)->toBe([1, 2, 3]);
});

test('invoice has many payments and exposes the latest payment', function () {
    $invoice = Invoice::factory()->create();
    $olderPayment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'project_id' => $invoice->project_id ?? Project::factory()->create()->id,
    ]);
    $latestPayment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'project_id' => $invoice->project_id ?? $olderPayment->project_id,
    ]);

    expect($invoice->payments)->toHaveCount(2)
        ->and($invoice->latestPayment)->toBeInstanceOf(Payment::class)
        ->and($invoice->latestPayment->id)->toBe($latestPayment->id);
});

test('scope active returns issued and paid invoices', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::Draft]);
    Invoice::factory()->issued()->create();
    Invoice::factory()->paid()->create();
    Invoice::factory()->cancelled()->create();

    $activeInvoices = Invoice::active()->get();

    expect($activeInvoices)->toHaveCount(2);
});

test('scope unpaid returns only issued invoices', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::Draft]);
    Invoice::factory()->issued()->create();
    Invoice::factory()->issued()->create();
    Invoice::factory()->paid()->create();

    $unpaidInvoices = Invoice::unpaid()->get();

    expect($unpaidInvoices)->toHaveCount(2);
});

test('scope paid returns only paid invoices', function () {
    Invoice::factory()->issued()->create();
    Invoice::factory()->paid()->create();
    Invoice::factory()->paid()->create();

    $paidInvoices = Invoice::paid()->get();

    expect($paidInvoices)->toHaveCount(2);
});

test('scope overdue returns issued invoices past due date', function () {
    Invoice::factory()->issued()->create(['due_date' => now()->addDays(5)]);
    Invoice::factory()->overdue()->create();
    Invoice::factory()->overdue()->create();
    Invoice::factory()->paid()->create(['due_date' => now()->subDays(5)]);

    $overdueInvoices = Invoice::overdue()->get();

    expect($overdueInvoices)->toHaveCount(2);

    test('is overdue returns true when issued and past due date', function () {
        $overdueInvoice = Invoice::factory()->overdue()->create();
        $notOverdueInvoice = Invoice::factory()->issued()->create(['due_date' => now()->addDays(5)]);
        $paidInvoice = Invoice::factory()->paid()->create(['due_date' => now()->subDays(1)]);

        expect($overdueInvoice->isOverdue())->toBeTrue()
            ->and($notOverdueInvoice->isOverdue())->toBeFalse()
            ->and($paidInvoice->isOverdue())->toBeFalse();
    });

    test('recalculate total updates invoice totals from items', function () {
        $invoice = Invoice::factory()->create([
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'subtotal' => 100000,
            'tax_amount' => 11000,
            'discount_amount' => 0,
            'total' => 111000,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'subtotal' => 200000,
            'tax_amount' => 22000,
            'discount_amount' => 10000,
            'total' => 212000,
        ]);

        $invoice->recalculateTotal();
        $invoice->refresh();

        expect($invoice->subtotal)->toBe(300000)
            ->and($invoice->tax_amount)->toBe(33000)
            ->and($invoice->discount_amount)->toBe(10000)
            ->and($invoice->total)->toBe(323000);
    });

    test('mark as paid updates status and paid_at', function () {
        $invoice = Invoice::factory()->issued()->create();

        $result = $invoice->markAsPaid();

        expect($result)->toBeTrue()
            ->and($invoice->status)->toBe(InvoiceStatus::Paid)
            ->and($invoice->paid_at)->not->toBeNull();
    });

    test('mark as paid fails if transition not allowed', function () {
        $invoice = Invoice::factory()->cancelled()->create();

        $result = $invoice->markAsPaid();

        expect($result)->toBeFalse()
            ->and($invoice->status)->toBe(InvoiceStatus::Cancelled);
    });

    test('invoice casts status to enum', function () {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Issued]);

        expect($invoice->status)->toBeInstanceOf(InvoiceStatus::class)
            ->and($invoice->status)->toBe(InvoiceStatus::Issued);
    });

    test('invoice soft deletes', function () {
        $invoice = Invoice::factory()->create();
        $invoiceId = $invoice->id;

        $invoice->delete();

        expect(Invoice::find($invoiceId))->toBeNull()
            ->and(Invoice::withTrashed()->find($invoiceId))->not->toBeNull();
    });

    test('invoice factory states work correctly', function () {
        $draft = Invoice::factory()->create();
        $issued = Invoice::factory()->issued()->create();
        $paid = Invoice::factory()->paid()->create();
        $cancelled = Invoice::factory()->cancelled()->create();
        $overdue = Invoice::factory()->overdue()->create();

        expect($draft->status)->toBe(InvoiceStatus::Draft)
            ->and($issued->status)->toBe(InvoiceStatus::Issued)
            ->and($issued->issue_date)->not->toBeNull()
            ->and($paid->status)->toBe(InvoiceStatus::Paid)
            ->and($paid->paid_at)->not->toBeNull()
            ->and($cancelled->status)->toBe(InvoiceStatus::Cancelled)
            ->and($overdue->status)->toBe(InvoiceStatus::Issued)
            ->and($overdue->isOverdue())->toBeTrue();
    });

});
