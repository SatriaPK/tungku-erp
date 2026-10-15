<?php

use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\ManualGateway;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('payment.manual.default_expiration_days', 7);
    Config::set('payment.manual.requires_approval', true);
});

test('gateway name returns manual', function () {
    $gateway = new ManualGateway;
    expect($gateway->getName())->toBe('manual');
});

test('creates charge without external API', function () {
    $gateway = new ManualGateway;
    $payment = Payment::factory()->create([
        'order_id' => 'PAY-20261009-00001',
        'amount' => 5000000,
    ]);

    $result = $gateway->createCharge($payment);

    expect($result)->toHaveKeys(['charge_id', 'payment_url', 'snap_token', 'expires_at'])
        ->and($result['charge_id'])->toBe('PAY-20261009-00001')
        ->and($result['payment_url'])->toBeNull()
        ->and($result['snap_token'])->toBeNull()
        ->and($result['expires_at'])->toBeInstanceOf(Carbon::class);

    // Verify expiration is 7 days from now (allow for sub-second timing difference)
    expect($result['expires_at']->diffInDays(now(), true))->toBeBetween(6, 7);
});

test('returns pending status for any charge', function () {
    $gateway = new ManualGateway;
    $status = $gateway->getChargeStatus('PAY-20261009-00001');

    expect($status)->toBe(PaymentStatus::Pending->value);
});

test('processes refund without external API', function () {
    $gateway = new ManualGateway;
    $payment = Payment::factory()->create([
        'order_id' => 'PAY-20261009-00001',
        'amount' => 5000000,
    ]);

    $result = $gateway->processRefund($payment, 5000000, 'Manual refund');

    expect($result)->toHaveKeys(['refund_id', 'status', 'refunded_at'])
        ->and($result['refund_id'])->toContain('manual-refund-')
        ->and($result['status'])->toBe('pending')
        ->and($result['refunded_at'])->toBeInstanceOf(Carbon::class);
});

test('webhook signature always returns true', function () {
    $request = Request::create('/webhook', 'POST', []);
    $gateway = new ManualGateway;

    expect($gateway->verifyWebhookSignature($request))->toBeTrue();
});

test('webhook payload returns normalized fields', function () {
    $request = Request::create('/webhook', 'POST', [
        'order_id' => 'PAY-20261009-00001',
        'transaction_id' => 'manual-txn-1',
        'status' => 'paid',
        'amount' => '5000000',
        'payment_method' => 'cash',
        'paid_at' => '2026-10-09 14:30:00',
    ]);
    $gateway = new ManualGateway;

    $payload = $gateway->parseWebhookPayload($request);

    expect($payload)->toMatchArray([
        'order_id' => 'PAY-20261009-00001',
        'transaction_id' => 'manual-txn-1',
        'status' => 'paid',
        'amount' => 5000000,
        'payment_method' => 'cash',
    ])->and($payload['paid_at'])->toEqual(Carbon::parse('2026-10-09 14:30:00'));
});
