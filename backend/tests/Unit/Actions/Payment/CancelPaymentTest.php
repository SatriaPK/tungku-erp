<?php

use App\Actions\Payment\CancelPayment;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->manager = User::factory()->manager()->create();
    $this->client = User::factory()->client()->create();
    $this->project = Project::factory()->for($this->client, 'client')->create();
});

it('cancels pending payment successfully', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Pending,
        'provider' => PaymentProvider::Midtrans,
    ]);

    $reason = 'Client requested cancellation';

    $result = app(CancelPayment::class)->execute($payment, $reason);

    expect($result->status)->toBe(PaymentStatus::Cancelled);
    expect(str_contains($result->notes, 'Cancelled:'))->toBeTrue();
    expect(str_contains($result->notes, $reason))->toBeTrue();
});

it('throws exception when cancelling paid payment', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    expect(fn () => app(CancelPayment::class)->execute($payment, 'reason'))
        ->toThrow(InvalidArgumentException::class, 'Only pending payments can be cancelled');
});

it('throws exception when cancelling expired payment', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Expired,
    ]);

    expect(fn () => app(CancelPayment::class)->execute($payment, 'reason'))
        ->toThrow(InvalidArgumentException::class, 'Only pending payments can be cancelled');
});

it('throws exception when cancelling already cancelled payment', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Cancelled,
    ]);

    expect(fn () => app(CancelPayment::class)->execute($payment, 'reason'))
        ->toThrow(InvalidArgumentException::class, 'Only pending payments can be cancelled');
});
