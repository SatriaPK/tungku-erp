<?php

use App\Actions\Payment\ConfirmManualPayment;
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

it('confirms cash payment successfully with custom paid_at', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Pending,
        'provider' => PaymentProvider::Cash,
    ]);

    $note = 'Confirmed via bank transfer, ref: TRF123456';
    $paidAt = new DateTime('2026-10-09 10:00:00');

    $result = app(ConfirmManualPayment::class)->execute($payment, $note, $paidAt);

    expect($result->status)->toBe(PaymentStatus::Paid)
        ->and($result->notes)->toBe($note)
        ->and($result->paid_at)->toEqual($paidAt);
});

it('confirms cash payment successfully', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Pending,
        'provider' => PaymentProvider::Cash,
    ]);

    $note = 'Paid in cash at studio';

    $result = app(ConfirmManualPayment::class)->execute($payment, $note);

    expect($result->status)->toBe(PaymentStatus::Paid)
        ->and($result->notes)->toBe($note)
        ->and($result->paid_at)->not->toBeNull();
});

it('uses current time when paid_at is not provided', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Pending,
        'provider' => PaymentProvider::Cash,
    ]);

    $result = app(ConfirmManualPayment::class)->execute($payment, 'Payment confirmed');

    expect($result->paid_at)->not->toBeNull()
        ->and($result->paid_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('throws exception when confirming already paid payment', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    expect(fn () => app(ConfirmManualPayment::class)->execute($payment, 'note'))
        ->toThrow(InvalidArgumentException::class, 'Cannot confirm payment with status: paid');
});

it('throws exception when confirming midtrans payment', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Pending,
        'provider' => PaymentProvider::Midtrans,
    ]);

    expect(fn () => app(ConfirmManualPayment::class)->execute($payment, 'note'))
        ->toThrow(InvalidArgumentException::class, 'Cannot manually confirm midtrans payment');
});

it('throws exception when confirming xendit payment', function () {
    $payment = Payment::factory()->create([
        'project_id' => $this->project->id,
        'status' => PaymentStatus::Pending,
        'provider' => PaymentProvider::Xendit,
    ]);

    expect(fn () => app(ConfirmManualPayment::class)->execute($payment, 'note'))
        ->toThrow(InvalidArgumentException::class, 'Use webhook for online payments');
});
