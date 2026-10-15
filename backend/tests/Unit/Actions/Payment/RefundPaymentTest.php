<?php

use App\Actions\Payment\RefundPayment;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\PaymentGatewayFactory;

beforeEach(function () {
    $this->manager = User::factory()->manager()->create();
    $this->client = User::factory()->client()->create();
    $this->project = Project::factory()->for($this->client, 'client')->create();

    PaymentGatewayFactory::fake();
});

afterEach(function () {
    PaymentGatewayFactory::clearFake();
});

it('refunds cash payment successfully', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Paid,
        'provider' => PaymentProvider::Cash,
        'amount' => 500000,
        'paid_at' => now(),
    ]);

    $refundAmount = 500000;
    $reason = 'Customer requested refund';

    $result = app(RefundPayment::class)->execute($payment, $refundAmount, $reason);

    expect($result->status)->toBe(PaymentStatus::Refunded);
    expect(str_contains($result->notes, 'Refunded:'))->toBeTrue();
    expect(str_contains($result->notes, $reason))->toBeTrue();
});

it('refunds online payment through gateway', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Paid,
        'provider' => PaymentProvider::Midtrans,
        'amount' => 500000,
        'paid_at' => now(),
        'gateway_charge_id' => 'charge-123',
    ]);

    $refundAmount = 500000;
    $reason = 'Customer requested refund';

    $result = app(RefundPayment::class)->execute($payment, $refundAmount, $reason);

    expect($result->status)->toBe(PaymentStatus::Refunded);
    expect(str_contains($result->notes, 'Refunded:'))->toBeTrue();
    expect(str_contains($result->notes, $reason))->toBeTrue();
    expect($result->gateway_response)->toHaveKey('refund');
});

it('supports partial refund', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Paid,
        'provider' => PaymentProvider::Cash,
        'amount' => 500000,
        'paid_at' => now(),
    ]);

    $partialAmount = 250000;
    $reason = 'Partial refund for cancelled service';

    $result = app(RefundPayment::class)->execute($payment, $partialAmount, $reason);

    expect($result->status)->toBe(PaymentStatus::Refunded);
});

it('throws exception when refunding unpaid payment', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Pending,
    ]);

    expect(fn () => app(RefundPayment::class)->execute($payment, 500000, 'reason'))
        ->toThrow(InvalidArgumentException::class, 'Only paid payments can be refunded');
});

it('throws exception when refund amount is zero', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    expect(fn () => app(RefundPayment::class)->execute($payment, 0, 'reason'))
        ->toThrow(InvalidArgumentException::class, 'Refund amount must be greater than zero');
});

it('throws exception when refund amount exceeds payment amount', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Paid,
        'amount' => 500000,
        'paid_at' => now(),
    ]);

    expect(fn () => app(RefundPayment::class)->execute($payment, 600000, 'reason'))
        ->toThrow(InvalidArgumentException::class, 'cannot exceed payment amount');
});
