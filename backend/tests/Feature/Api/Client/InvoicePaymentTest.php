<?php

use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    PaymentGatewayFactory::fake();
});

afterEach(function () {
    PaymentGatewayFactory::clearFake();
});

it('client can list their own invoices', function () {
    $client = User::factory()->client()->create();
    $otherClient = User::factory()->client()->create();

    $project = Project::factory()->create(['client_id' => $client->id]);

    $ownInvoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => InvoiceStatus::Issued,
    ]);

    Invoice::factory()->create([
        'client_id' => $otherClient->id,
        'status' => InvoiceStatus::Issued,
    ]);

    Sanctum::actingAs($client);

    $response = $this->getJson('/api/v1/client/invoices');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownInvoice->id);
});

it('client can view their own invoice detail', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => InvoiceStatus::Issued,
        'total' => 100000,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Test Item',
        'total' => 100000,
    ]);

    Sanctum::actingAs($client);

    $response = $this->getJson("/api/v1/client/invoices/{$invoice->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $invoice->id)
        ->assertJsonPath('data.invoice_number', $invoice->invoice_number)
        ->assertJsonPath('data.total', 100000)
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonStructure([
            'data' => [
                'id',
                'invoice_number',
                'status',
                'total',
                'items',
                'client',
                'project',
            ],
        ]);
});

it('client cannot view another clients invoice', function () {
    $client = User::factory()->client()->create();
    $otherClient = User::factory()->client()->create();

    $invoice = Invoice::factory()->create([
        'client_id' => $otherClient->id,
        'status' => InvoiceStatus::Issued,
    ]);

    Sanctum::actingAs($client);

    $response = $this->getJson("/api/v1/client/invoices/{$invoice->id}");

    $response->assertForbidden();
});

it('client can initiate payment for their invoice', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => InvoiceStatus::Issued,
        'total' => 100000,
    ]);

    Sanctum::actingAs($client);

    $response = $this->postJson("/api/v1/client/invoices/{$invoice->id}/initiate-payment", [
        'gateway' => 'midtrans',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'order_id',
                'amount',
                'status',
                'provider',
                'payment_url',
                'snap_token',
            ],
        ])
        ->assertJsonPath('data.amount', 100000)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.provider', 'midtrans');

    $this->assertDatabaseHas('payments', [
        'invoice_id' => $invoice->id,
        'amount' => 100000,
        'status' => PaymentStatus::Pending->value,
    ]);
});

it('client cannot initiate payment for draft invoice', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => InvoiceStatus::Draft,
        'total' => 100000,
    ]);

    Sanctum::actingAs($client);

    $response = $this->postJson("/api/v1/client/invoices/{$invoice->id}/initiate-payment", [
        'gateway' => 'midtrans',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.invoice.0', 'Invoice must be in Issued status to initiate payment.');
});

it('client cannot initiate payment for another clients invoice', function () {
    $client = User::factory()->client()->create();
    $otherClient = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $otherClient->id]);

    $invoice = Invoice::factory()->create([
        'client_id' => $otherClient->id,
        'project_id' => $project->id,
        'status' => InvoiceStatus::Issued,
        'total' => 100000,
    ]);

    Sanctum::actingAs($client);

    $response = $this->postJson("/api/v1/client/invoices/{$invoice->id}/initiate-payment", [
        'gateway' => 'midtrans',
    ]);

    $response->assertForbidden();
});

it('client can view their payment status', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => InvoiceStatus::Issued,
    ]);

    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'project_id' => $project->id,
        'amount' => 100000,
        'status' => PaymentStatus::Pending,
    ]);

    Sanctum::actingAs($client);

    $response = $this->getJson("/api/v1/client/payments/{$payment->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $payment->id)
        ->assertJsonPath('data.amount', 100000)
        ->assertJsonPath('data.status', 'pending');
});

it('client cannot view another clients payment', function () {
    $client = User::factory()->client()->create();
    $otherClient = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $otherClient->id]);

    $invoice = Invoice::factory()->create([
        'client_id' => $otherClient->id,
        'project_id' => $project->id,
    ]);

    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'project_id' => $project->id,
    ]);

    Sanctum::actingAs($client);

    $response = $this->getJson("/api/v1/client/payments/{$payment->id}");

    $response->assertForbidden();
});

it('validates gateway parameter when initiating payment', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'status' => InvoiceStatus::Issued,
        'total' => 100000,
    ]);

    Sanctum::actingAs($client);

    $response = $this->postJson("/api/v1/client/invoices/{$invoice->id}/initiate-payment", [
        'gateway' => 'invalid-gateway',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('gateway');
});
