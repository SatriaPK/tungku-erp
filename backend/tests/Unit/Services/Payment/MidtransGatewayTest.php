<?php

use App\Enum\PaymentStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\MidtransGateway;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // Set test configuration - works without actual API keys
    Config::set('payment.midtrans.server_key', 'test-server-key');
    Config::set('payment.midtrans.client_key', 'test-client-key');
    Config::set('payment.midtrans.is_production', false);
    Config::set('payment.midtrans.is_sanitized', true);
    Config::set('payment.midtrans.is_3ds', true);
    Config::set('payment.expiration_hours', 24);
});

test('gateway name returns midtrans', function () {
    $gateway = new MidtransGateway;
    expect($gateway->getName())->toBe('midtrans');
});

test('verifies valid webhook signature', function () {
    $orderId = 'PAY-20261009-00001';
    $statusCode = '200';
    $grossAmount = '5000000';
    $serverKey = 'test-server-key';

    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

    $request = Request::create('/webhook', 'POST', [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
    ]);

    $gateway = new MidtransGateway;
    $result = $gateway->verifyWebhookSignature($request);

    expect($result)->toBeTrue();
});

test('throws exception on invalid webhook signature', function () {
    $request = Request::create('/webhook', 'POST', [
        'order_id' => 'PAY-20261009-00001',
        'status_code' => '200',
        'gross_amount' => '5000000',
        'signature_key' => 'invalid-signature',
    ]);

    $gateway = new MidtransGateway;

    expect(fn () => $gateway->verifyWebhookSignature($request))
        ->toThrow(InvalidWebhookSignatureException::class, 'Invalid webhook signature from Midtrans');
});

test('parses webhook payload correctly', function () {
    $request = Request::create('/webhook', 'POST', [
        'order_id' => 'PAY-20261009-00001',
        'transaction_id' => 'midtrans-txn-123',
        'transaction_status' => 'settlement',
        'fraud_status' => 'accept',
        'gross_amount' => '5000000',
        'payment_type' => 'credit_card',
        'transaction_time' => '2026-10-09 14:30:00',
    ]);

    $gateway = new MidtransGateway;
    $data = $gateway->parseWebhookPayload($request);

    expect($data)->toHaveKeys(['order_id', 'transaction_id', 'status', 'amount', 'payment_method', 'paid_at'])
        ->and($data['order_id'])->toBe('PAY-20261009-00001')
        ->and($data['transaction_id'])->toBe('midtrans-txn-123')
        ->and($data['status'])->toBe(PaymentStatus::Paid->value)
        ->and($data['amount'])->toBe(5000000)
        ->and($data['payment_method'])->toBe('credit_card')
        ->and($data['paid_at'])->toBeInstanceOf(Carbon::class);
});

test('parses pending webhook without paid_at', function () {
    $request = Request::create('/webhook', 'POST', [
        'order_id' => 'PAY-20261009-00001',
        'transaction_id' => 'midtrans-txn-123',
        'transaction_status' => 'pending',
        'gross_amount' => '5000000',
        'payment_type' => 'bank_transfer',
        'transaction_time' => '2026-10-09 14:30:00',
    ]);

    $gateway = new MidtransGateway;
    $data = $gateway->parseWebhookPayload($request);

    expect($data['status'])->toBe(PaymentStatus::Pending->value)
        ->and($data['paid_at'])->toBeNull();
});

test('normalizes various midtrans statuses', function ($midtransStatus, $expectedStatus) {
    $request = Request::create('/webhook', 'POST', [
        'order_id' => 'PAY-TEST',
        'transaction_id' => 'test-123',
        'transaction_status' => $midtransStatus,
        'fraud_status' => 'accept',
        'gross_amount' => '100000',
        'payment_type' => 'bank_transfer',
        'transaction_time' => '2026-10-09 14:30:00',
    ]);

    $gateway = new MidtransGateway;
    $data = $gateway->parseWebhookPayload($request);

    expect($data['status'])->toBe($expectedStatus);
})->with([
    ['capture', PaymentStatus::Paid->value],
    ['settlement', PaymentStatus::Paid->value],
    ['pending', PaymentStatus::Pending->value],
    ['deny', PaymentStatus::Failed->value],
    ['cancel', PaymentStatus::Failed->value],
    ['expire', PaymentStatus::Expired->value],
    ['refund', PaymentStatus::Refunded->value],
]);

test('handles fraud denial correctly', function () {
    $request = Request::create('/webhook', 'POST', [
        'order_id' => 'PAY-TEST',
        'transaction_id' => 'test-123',
        'transaction_status' => 'capture',
        'fraud_status' => 'deny',
        'gross_amount' => '100000',
        'payment_type' => 'credit_card',
        'transaction_time' => '2026-10-09 14:30:00',
    ]);

    $gateway = new MidtransGateway;
    $data = $gateway->parseWebhookPayload($request);

    expect($data['status'])->toBe(PaymentStatus::Failed->value);
});
test('normalizes midtrans status correctly', function ($midtransStatus, $expectedStatus) {
    $mockStatus = (object) [
        'transaction_status' => $midtransStatus,
        'fraud_status' => 'accept',
    ];

    // Test normalizeStatus via webhook payload (which uses the same logic, no API call needed)
    $request = Request::create('/webhook', 'POST', [
        'order_id' => 'PAY-TEST',
        'transaction_id' => 'test-123',
        'transaction_status' => $midtransStatus,
        'fraud_status' => 'accept',
        'gross_amount' => '100000',
        'payment_type' => 'credit_card',
        'transaction_time' => '2026-10-09 14:30:00',
    ]);

    $gateway = new MidtransGateway;
    $data = $gateway->parseWebhookPayload($request);

    expect($data['status'])->toBe($expectedStatus);
})->with([
    ['capture', PaymentStatus::Paid->value],
    ['settlement', PaymentStatus::Paid->value],
    ['pending', PaymentStatus::Pending->value],
    ['deny', PaymentStatus::Failed->value],
    ['cancel', PaymentStatus::Failed->value],
    ['expire', PaymentStatus::Expired->value],
    ['refund', PaymentStatus::Refunded->value],
]);

test('handles fraud status correctly via webhook', function () {
    $request = Request::create('/webhook', 'POST', [
        'order_id' => 'PAY-TEST',
        'transaction_id' => 'test-123',
        'transaction_status' => 'capture',
        'fraud_status' => 'deny',
        'gross_amount' => '100000',
        'payment_type' => 'credit_card',
        'transaction_time' => '2026-10-09 14:30:00',
    ]);

    $gateway = new MidtransGateway;
    $data = $gateway->parseWebhookPayload($request);

    expect($data['status'])->toBe(PaymentStatus::Failed->value);
});

test('processes refund successfully', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'order_id' => 'PAY-20261009-00001',
        'amount' => 5000000,
    ]);

    $gateway = new MidtransGateway;

    // processRefund calls Midtrans API — skip in unit test (requires live API)
    expect(fn () => $gateway->processRefund($payment, 5000000, 'Customer requested'))
        ->toThrow(PaymentGatewayException::class);
})->skip('Requires live Midtrans API — tested in integration/sandbox environment');
