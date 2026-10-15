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
        'provider' => PaymentProvider::Midtrans,
        'order_id' => 'PAY-20261009-00001',
        'gateway_charge_id' => 'midtrans-charge-123',
    ]);
});

test('receives valid midtrans webhook for settlement', function () {
    $payload = buildMidtransPayload($this->payment, 'settlement');

    $response = $this->postJson('/api/v1/webhooks/payment/midtrans', $payload);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Webhook received and queued',
        ]);

    // Verify webhook was logged
    $this->assertDatabaseHas('webhook_logs', [
        'gateway' => 'midtrans',
        'order_id' => $this->payment->order_id,
        'event_type' => 'settlement',
        'status' => 'received',
    ]);

    // Verify job was dispatched
    Queue::assertPushed(HandlePaymentWebhook::class);
});

test('receives valid midtrans webhook for capture', function () {
    $payload = buildMidtransPayload($this->payment, 'capture');

    $response = $this->postJson('/api/v1/webhooks/payment/midtrans', $payload);

    $response->assertOk();

    expect(WebhookLog::count())->toBe(1);
    Queue::assertPushed(HandlePaymentWebhook::class);
});

test('receives valid midtrans webhook for pending', function () {
    $payload = buildMidtransPayload($this->payment, 'pending');

    $response = $this->postJson('/api/v1/webhooks/payment/midtrans', $payload);

    $response->assertOk();

    $log = WebhookLog::first();
    expect($log->event_type)->toBe('pending');
});

test('receives valid midtrans webhook for expire', function () {
    $payload = buildMidtransPayload($this->payment, 'expire');

    $response = $this->postJson('/api/v1/webhooks/payment/midtrans', $payload);

    $response->assertOk();
    Queue::assertPushed(HandlePaymentWebhook::class);
});

test('receives valid midtrans webhook for deny', function () {
    $payload = buildMidtransPayload($this->payment, 'deny');

    $response = $this->postJson('/api/v1/webhooks/payment/midtrans', $payload);

    $response->assertOk();
    Queue::assertPushed(HandlePaymentWebhook::class);
});

test('rejects midtrans webhook with invalid signature', function () {
    $payload = buildMidtransPayload($this->payment, 'settlement');
    $payload['signature_key'] = 'invalid-signature';

    $response = $this->postJson('/api/v1/webhooks/payment/midtrans', $payload);

    $response->assertStatus(401)
        ->assertJson([
            'status' => 'error',
            'message' => 'Invalid signature',
        ]);

    expect(WebhookLog::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('handles duplicate midtrans webhooks idempotently', function () {
    $payload = buildMidtransPayload($this->payment, 'settlement');

    $response1 = $this->postJson('/api/v1/webhooks/payment/midtrans', $payload);
    $response1->assertOk();

    $log = WebhookLog::first();
    $log->markAsProcessed();

    $response2 = $this->postJson('/api/v1/webhooks/payment/midtrans', $payload);
    $response2->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Webhook already processed',
        ]);

    expect(WebhookLog::count())->toBe(1);
    Queue::assertPushed(HandlePaymentWebhook::class, 1);
});

test('handles midtrans webhook with fraud status challenge', function () {
    $payload = buildMidtransPayload($this->payment, 'capture');
    $payload['fraud_status'] = 'challenge';

    $response = $this->postJson('/api/v1/webhooks/payment/midtrans', $payload);

    $response->assertOk();
    expect(WebhookLog::first())->not->toBeNull();
});

test('rejects webhook for unsupported gateway', function () {
    $response = $this->postJson('/api/v1/webhooks/payment/invalid-gateway', ['order_id' => 'test']);

    $response->assertStatus(400)->assertJson(['message' => 'Unsupported gateway']);
});

test('stores webhook headers correctly', function () {
    $payload = buildMidtransPayload($this->payment, 'settlement');

    $this->postJson('/api/v1/webhooks/payment/midtrans', $payload, [
        'User-Agent' => 'Midtrans-HTTP-Notification',
    ]);

    $log = WebhookLog::first();
    expect($log->headers)->toBeArray()->toHaveKey('user-agent');
});

test('logs webhook payload correctly', function () {
    $payload = buildMidtransPayload($this->payment, 'settlement');

    $this->postJson('/api/v1/webhooks/payment/midtrans', $payload);

    $log = WebhookLog::first();
    expect($log->payload)
        ->toBeArray()
        ->toHaveKey('order_id')
        ->and($log->payload['order_id'])->toBe($this->payment->order_id);
});

function buildMidtransPayload(Payment $payment, string $transactionStatus): array
{
    $orderId = $payment->order_id;
    $statusCode = '200';
    $grossAmount = (string) $payment->amount;
    $serverKey = config('payment.midtrans.server_key');

    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

    return [
        'transaction_time' => now()->toDateTimeString(),
        'transaction_status' => $transactionStatus,
        'transaction_id' => 'midtrans-txn-'.uniqid(),
        'status_message' => 'midtrans payment notification',
        'status_code' => $statusCode,
        'signature_key' => $signatureKey,
        'payment_type' => 'credit_card',
        'order_id' => $orderId,
        'merchant_id' => 'test-merchant',
        'gross_amount' => $grossAmount,
        'fraud_status' => 'accept',
        'currency' => 'IDR',
    ];
}
