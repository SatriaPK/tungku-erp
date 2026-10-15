<?php

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Jobs\HandlePaymentWebhook;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    config(['payment.xendit.webhook_token' => 'test-webhook-token']);

    $this->client = User::factory()->client()->create();
    $this->project = Project::factory()->create([
        'client_id' => $this->client->id,
    ]);

    $this->invoice = Invoice::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $this->payment = Payment::factory()->create([
        'invoice_id' => $this->invoice->id,
        'project_id' => $this->project->id,
        'type' => PaymentType::Po,
        'amount' => 500000,
        'status' => PaymentStatus::Pending,
        'provider' => PaymentProvider::Xendit,
        'order_id' => 'PAY-20261009-00002',
        'gateway_charge_id' => 'xendit-charge-123',
    ]);
});

test('receives valid xendit webhook for paid', function () {
    $payload = buildXenditPayload($this->payment, 'PAID', $this->client);

    $response = $this->postJson('/api/v1/webhooks/payment/xendit', $payload, [
        'x-callback-token' => config('payment.xendit.webhook_token'),
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Webhook received and queued',
        ]);

    $this->assertDatabaseHas('webhook_logs', [
        'gateway' => 'xendit',
        'order_id' => $this->payment->order_id,
        'event_type' => 'PAID',
        'status' => 'received',
    ]);

    Queue::assertPushed(HandlePaymentWebhook::class);
});

test('receives valid xendit webhook for expired', function () {
    $payload = buildXenditPayload($this->payment, 'EXPIRED', $this->client);

    $response = $this->postJson('/api/v1/webhooks/payment/xendit', $payload, [
        'x-callback-token' => config('payment.xendit.webhook_token'),
    ]);

    $response->assertOk();
    Queue::assertPushed(HandlePaymentWebhook::class);
});

test('receives valid xendit webhook for pending', function () {
    $payload = buildXenditPayload($this->payment, 'PENDING', $this->client);

    $response = $this->postJson('/api/v1/webhooks/payment/xendit', $payload, [
        'x-callback-token' => config('payment.xendit.webhook_token'),
    ]);

    $response->assertOk();
    Queue::assertPushed(HandlePaymentWebhook::class);
});

test('rejects xendit webhook with invalid token', function () {
    $payload = buildXenditPayload($this->payment, 'PAID', $this->client);

    $response = $this->postJson('/api/v1/webhooks/payment/xendit', $payload, [
        'x-callback-token' => 'invalid-token',
    ]);

    $response->assertStatus(401);
    expect(WebhookLog::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('rejects xendit webhook with missing token', function () {
    $payload = buildXenditPayload($this->payment, 'PAID', $this->client);

    $response = $this->postJson('/api/v1/webhooks/payment/xendit', $payload);

    $response->assertStatus(401);
    expect(WebhookLog::count())->toBe(0);
});

test('handles duplicate xendit webhooks idempotently', function () {
    $payload = buildXenditPayload($this->payment, 'PAID', $this->client);
    $headers = ['x-callback-token' => config('payment.xendit.webhook_token')];

    $response1 = $this->postJson('/api/v1/webhooks/payment/xendit', $payload, $headers);
    $response1->assertOk();

    $log = WebhookLog::first();
    $log->markAsProcessed();

    $response2 = $this->postJson('/api/v1/webhooks/payment/xendit', $payload, $headers);
    $response2->assertOk();

    expect(WebhookLog::count())->toBe(1);
    Queue::assertPushed(HandlePaymentWebhook::class, 1);
});

function buildXenditPayload(Payment $payment, string $status, User $client): array
{
    return [
        'id' => 'xendit-txn-'.uniqid(),
        'external_id' => $payment->order_id,
        'user_id' => 'xendit-user-id',
        'is_high' => false,
        'payment_method' => 'BANK_TRANSFER',
        'status' => $status,
        'merchant_name' => 'Test Merchant',
        'amount' => $payment->amount,
        'paid_amount' => $status === 'PAID' ? $payment->amount : 0,
        'bank_code' => 'BCA',
        'paid_at' => $status === 'PAID' ? now()->toIso8601String() : null,
        'payer_email' => $client->email,
        'description' => 'Test invoice',
        'created' => now()->subMinutes(10)->toIso8601String(),
        'updated' => now()->toIso8601String(),
        'currency' => 'IDR',
    ];
}
