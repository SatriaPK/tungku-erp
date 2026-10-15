<?php

use App\Enum\PaymentStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\XenditGateway;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Set test configuration - works without actual API keys
    Config::set('payment.xendit.secret_key', 'test-secret-key');
    Config::set('payment.xendit.webhook_token', 'test-webhook-token');
    Config::set('payment.xendit.is_production', false);
    Config::set('payment.expiration_hours', 24);
    Config::set('app.url', 'http://localhost:8000');
});

test('gateway name returns xendit', function () {
    $gateway = new XenditGateway;
    expect($gateway->getName())->toBe('xendit');
});

test('creates invoice successfully', function () {
    // Mock HTTP response - no real API call needed
    Http::fake([
        'api.xendit.co/v2/invoices' => Http::response([
            'id' => 'xendit-inv-123',
            'invoice_url' => 'https://checkout.xendit.co/web/xendit-inv-123',
            'external_id' => 'PAY-20261009-00001',
        ], 200),
    ]);

    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'order_id' => 'PAY-20261009-00001',
        'amount' => 5000000,
    ]);

    $gateway = new XenditGateway;
    $result = $gateway->createCharge($payment);

    expect($result)->toHaveKeys(['charge_id', 'payment_url', 'snap_token', 'expires_at'])
        ->and($result['charge_id'])->toBe('xendit-inv-123')
        ->and($result['payment_url'])->toContain('checkout.xendit.co')
        ->and($result['snap_token'])->toBeNull()
        ->and($result['expires_at'])->toBeInstanceOf(Carbon::class);
});

test('gets charge status successfully', function () {
    Http::fake([
        'api.xendit.co/v2/invoices/*' => Http::response([
            'id' => 'xendit-inv-123',
            'status' => 'PAID',
        ], 200),
    ]);

    $gateway = new XenditGateway;
    $status = $gateway->getChargeStatus('xendit-inv-123');

    expect($status)->toBe(PaymentStatus::Paid->value);
});

test('normalizes xendit status correctly', function ($xenditStatus, $expectedStatus) {
    Http::fake([
        'api.xendit.co/v2/invoices/*' => Http::response([
            'id' => 'xendit-inv-123',
            'status' => $xenditStatus,
        ], 200),
    ]);

    $gateway = new XenditGateway;
    $status = $gateway->getChargeStatus('xendit-inv-123');

    expect($status)->toBe($expectedStatus);
})->with([
    ['PAID', PaymentStatus::Paid->value],
    ['SETTLED', PaymentStatus::Paid->value],
    ['PENDING', PaymentStatus::Pending->value],
    ['EXPIRED', PaymentStatus::Expired->value],
    ['FAILED', PaymentStatus::Failed->value],
]);

test('processes refund successfully', function () {
    Http::fake([
        'api.xendit.co/refunds' => Http::response([
            'id' => 'refund-123',
            'status' => 'SUCCEEDED',
            'created' => '2026-10-09T14:30:00Z',
        ], 200),
    ]);

    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'order_id' => 'PAY-20261009-00001',
        'gateway_charge_id' => 'xendit-inv-123',
        'amount' => 5000000,
    ]);

    $gateway = new XenditGateway;
    $result = $gateway->processRefund($payment, 5000000, 'Customer requested');

    expect($result)->toHaveKeys(['refund_id', 'status', 'refunded_at'])
        ->and($result['refund_id'])->toBe('refund-123')
        ->and($result['status'])->toBe('SUCCEEDED')
        ->and($result['refunded_at'])->toBeInstanceOf(Carbon::class);
});

test('verifies valid webhook signature', function () {
    $request = Request::create('/webhook', 'POST', [
        'external_id' => 'PAY-20261009-00001',
        'status' => 'PAID',
    ]);
    $request->headers->set('x-callback-token', 'test-webhook-token');

    $gateway = new XenditGateway;
    $result = $gateway->verifyWebhookSignature($request);

    expect($result)->toBeTrue();
});

test('throws exception on invalid webhook signature', function () {
    $request = Request::create('/webhook', 'POST', [
        'external_id' => 'PAY-20261009-00001',
        'status' => 'PAID',
    ]);
    $request->headers->set('x-callback-token', 'invalid-token');

    $gateway = new XenditGateway;

    expect(fn () => $gateway->verifyWebhookSignature($request))
        ->toThrow(InvalidWebhookSignatureException::class, 'Invalid webhook signature from Xendit');
});

test('parses webhook payload correctly', function () {
    $request = Request::create('/webhook', 'POST', [
        'external_id' => 'PAY-20261009-00001',
        'id' => 'xendit-inv-123',
        'status' => 'PAID',
        'amount' => 5000000,
        'payment_method' => 'CREDIT_CARD',
        'paid_at' => '2026-10-09T14:30:00Z',
    ]);

    $gateway = new XenditGateway;
    $data = $gateway->parseWebhookPayload($request);

    expect($data)->toHaveKeys(['order_id', 'transaction_id', 'status', 'amount', 'payment_method', 'paid_at'])
        ->and($data['order_id'])->toBe('PAY-20261009-00001')
        ->and($data['transaction_id'])->toBe('xendit-inv-123')
        ->and($data['status'])->toBe(PaymentStatus::Paid->value)
        ->and($data['amount'])->toBe(5000000)
        ->and($data['payment_method'])->toBe('CREDIT_CARD')
        ->and($data['paid_at'])->toBeInstanceOf(Carbon::class);
});

test('parses pending webhook without paid_at', function () {
    $request = Request::create('/webhook', 'POST', [
        'external_id' => 'PAY-20261009-00001',
        'id' => 'xendit-inv-123',
        'status' => 'PENDING',
        'amount' => 5000000,
        'payment_method' => 'BANK_TRANSFER',
    ]);

    $gateway = new XenditGateway;
    $data = $gateway->parseWebhookPayload($request);

    expect($data['status'])->toBe(PaymentStatus::Pending->value)
        ->and($data['paid_at'])->toBeNull();
});
