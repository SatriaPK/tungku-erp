<?php

use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Jobs\HandlePaymentWebhook;
use App\Models\Payment;
use App\Models\WebhookLog;

beforeEach(function () {
    $this->payment = Payment::factory()->pending()->midtrans()->create([
        'order_id' => 'PAY-20261009-TEST',
        'amount' => 500000,
        'type' => PaymentType::Po,
        'expires_at' => now()->addDay(),
    ]);

    $this->webhookLog = WebhookLog::create([
        'gateway' => 'midtrans',
        'event_type' => 'settlement',
        'transaction_id' => 'txn-123456',
        'order_id' => $this->payment->order_id,
        'payload' => ['test' => 'data'],
        'headers' => ['x-signature' => 'valid'],
        'status' => 'received',
    ]);
});

test('job processes paid status correctly', function () {
    $parsedData = [
        'order_id' => $this->payment->order_id,
        'transaction_id' => 'txn-123456',
        'status' => PaymentStatus::Paid->value,
        'amount' => 500000,
        'payment_method' => 'credit_card',
        'paid_at' => now(),
    ];

    $job = new HandlePaymentWebhook($this->webhookLog, $parsedData);
    $job->handle();

    // Verify payment was updated
    $this->payment->refresh();
    expect($this->payment->status)->toBe(PaymentStatus::Paid)
        ->and($this->payment->gateway_response)->toBeArray()->not->toBeEmpty();

    // Verify webhook log was updated
    $this->webhookLog->refresh();
    expect($this->webhookLog->status)->toBe('processed')
        ->and($this->webhookLog->processed_at)->not->toBeNull();
});

test('job processes expired status correctly', function () {
    $this->payment->expires_at = now()->subHours(1);
    $this->payment->save();

    $parsedData = [
        'order_id' => $this->payment->order_id,
        'transaction_id' => 'txn-123456',
        'status' => PaymentStatus::Expired->value,
        'amount' => 500000,
        'payment_method' => null,
        'paid_at' => null,
    ];

    $job = new HandlePaymentWebhook($this->webhookLog, $parsedData);
    $job->handle();

    $this->payment->refresh();
    expect($this->payment->status)->toBe(PaymentStatus::Expired);

    $this->webhookLog->refresh();
    expect($this->webhookLog->status)->toBe('processed');
});

test('job processes failed status correctly', function () {
    $parsedData = [
        'order_id' => $this->payment->order_id,
        'transaction_id' => 'txn-123456',
        'status' => PaymentStatus::Failed->value,
        'amount' => 500000,
        'payment_method' => 'credit_card',
        'paid_at' => null,
    ];

    $job = new HandlePaymentWebhook($this->webhookLog, $parsedData);
    $job->handle();

    $this->payment->refresh();
    expect($this->payment->status)->toBe(PaymentStatus::Failed);
});

test('job handles missing payment gracefully', function () {
    $parsedData = [
        'order_id' => 'NONEXISTENT-ORDER',
        'transaction_id' => 'txn-123456',
        'status' => PaymentStatus::Paid->value,
        'amount' => 500000,
    ];

    $job = new HandlePaymentWebhook($this->webhookLog, $parsedData);
    $job->handle();

    $this->webhookLog->refresh();
    expect($this->webhookLog->status)->toBe('failed')
        ->and($this->webhookLog->error_message)->toBe('Payment not found');
});

test('job skips processing if already processed', function () {
    // Mark as processed
    $this->webhookLog->markAsProcessed();

    // Change payment status to something else to verify it doesn't change
    $this->payment->update(['status' => PaymentStatus::Pending]);

    $parsedData = [
        'order_id' => $this->payment->order_id,
        'transaction_id' => 'txn-123456',
        'status' => PaymentStatus::Paid->value,
        'amount' => 500000,
    ];

    $job = new HandlePaymentWebhook($this->webhookLog, $parsedData);
    $job->handle();

    $this->payment->refresh();
    expect($this->payment->status)->toBe(PaymentStatus::Pending); // Should not change
});

test('job skips transition if payment already terminal', function () {
    // Payment is already Paid
    $this->payment->update(['status' => PaymentStatus::Paid]);

    $parsedData = [
        'order_id' => $this->payment->order_id,
        'transaction_id' => 'txn-123456',
        'status' => PaymentStatus::Expired->value, // Trying to expire a paid payment
        'amount' => 500000,
    ];

    $job = new HandlePaymentWebhook($this->webhookLog, $parsedData);
    $job->handle();

    $this->payment->refresh();
    expect($this->payment->status)->toBe(PaymentStatus::Paid); // Status should remains Paid

    $this->webhookLog->refresh();
    expect($this->webhookLog->status)->toBe('processed');
});
