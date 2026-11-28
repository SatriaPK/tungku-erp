<?php

use App\Actions\Payment\InitiatePayment;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\RecordingSession;
use App\Models\User;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock gateway factory
    PaymentGatewayFactory::fake();
});

afterEach(function () {
    PaymentGatewayFactory::clearFake();
});

it('creates payment for issued invoice', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => InvoiceStatus::Issued,
        'total' => 100000,
    ]);

    $payment = app(InitiatePayment::class)->execute($invoice, 'xendit');

    expect($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->invoice_id)->toBe($invoice->id)
        ->and($payment->project_id)->toBe($project->id)
        ->and($payment->amount)->toBe(100000)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->provider)->toBe(PaymentProvider::Xendit)
        ->and($payment->type)->toBe(PaymentType::Po)
        ->and($payment->order_id)->toContain('INV-')
        ->and($payment->gateway_charge_id)->not->toBeNull()
        ->and($payment->payment_url)->not->toBeNull();
});

it('throws exception if invoice is not issued', function () {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Draft,
    ]);

    app(InitiatePayment::class)->execute($invoice, 'xendit');
})->throws(InvalidArgumentException::class, 'Invoice must be in Issued status');

it('throws exception if invoice total is zero', function () {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Issued,
        'total' => 0,
    ]);

    app(InitiatePayment::class)->execute($invoice, 'xendit');
})->throws(InvalidArgumentException::class, 'Invoice total must be greater than zero');

it('throws exception if invoice already has pending payment', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => InvoiceStatus::Issued,
        'total' => 100000,
    ]);

    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'project_id' => $project->id,
        'status' => PaymentStatus::Pending,
    ]);

    app(InitiatePayment::class)->execute($invoice, 'xendit');
})->throws(InvalidArgumentException::class, 'Invoice already has an active payment');

it('throws exception if invoice already has paid payment', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => InvoiceStatus::Issued,
        'total' => 100000,
    ]);

    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'project_id' => $project->id,
        'status' => PaymentStatus::Paid,
    ]);

    app(InitiatePayment::class)->execute($invoice, 'xendit');
})->throws(InvalidArgumentException::class, 'Invoice already has an active payment');

it('creates a new payment attempt after a failed attempt, including with another gateway', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => InvoiceStatus::Issued,
        'total' => 100000,
    ]);
    Payment::factory()->forInvoice($invoice)->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Failed,
        'order_id' => 'FAILED-ATTEMPT',
    ]);

    $retry = app(InitiatePayment::class)->execute($invoice, 'xendit');

    expect($invoice->payments()->count())->toBe(2)
        ->and($retry->status)->toBe(PaymentStatus::Pending)
        ->and($retry->provider)->toBe(PaymentProvider::Xendit)
        ->and($retry->order_id)->not->toBe('FAILED-ATTEMPT');
});

it('supports xendit gateway', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => InvoiceStatus::Issued,
        'total' => 100000,
    ]);

    $payment = app(InitiatePayment::class)->execute($invoice, 'xendit');

    expect($payment->provider)->toBe(PaymentProvider::Xendit);
});

it('throws exception for unsupported gateway', function () {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Issued,
        'total' => 100000,
    ]);

    app(InitiatePayment::class)->execute($invoice, 'invalid-gateway');
})->throws(InvalidArgumentException::class, 'Unsupported payment gateway');

it('resolves project_id from recording session if invoice has no project', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $session = RecordingSession::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
    ]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => null,
        'recording_session_id' => $session->id,
        'status' => InvoiceStatus::Issued,
        'total' => 100000,
    ]);

    $payment = app(InitiatePayment::class)->execute($invoice, 'xendit');

    expect($payment->project_id)->toBe($project->id);
});

it('throws exception if invoice has no project and no recording session', function () {
    $client = User::factory()->client()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => null,
        'recording_session_id' => null,
        'status' => InvoiceStatus::Issued,
        'total' => 100000,
    ]);

    app(InitiatePayment::class)->execute($invoice, 'xendit');
})->throws(InvalidArgumentException::class, 'Invoice must be associated with a project');
