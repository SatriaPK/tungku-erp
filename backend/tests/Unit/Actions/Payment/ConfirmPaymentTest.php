<?php

use App\Actions\Payment\ConfirmPayment;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('confirms pending payment successfully', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Pending,
        'expires_at' => now()->addHours(24),
    ]);

    $action = new ConfirmPayment;
    $result = $action->execute($payment, 'txn-'.time().'-'.rand(1000, 9999), 'credit_card');

    expect($result->status)->toBe(PaymentStatus::Paid)
        ->and($result->provider_transaction_id)->not()->toBeNull()
        ->and($result->payment_method)->toBe('credit_card')
        ->and($result->paid_at)->not()->toBeNull()
        ->and($result->paid_at->isToday())->toBeTrue();
});

test('stores raw webhook payload', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Pending,
    ]);

    $payload = ['status' => 'settlement', 'transaction_id' => 'test-123'];

    $action = new ConfirmPayment;
    $result = $action->execute($payment, 'txn-'.time().'-'.rand(1000, 9999), 'bank_transfer', $payload);

    expect($result->raw_payload)->toBe($payload);
});

test('throws exception when confirming already paid payment', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $action = new ConfirmPayment;

    expect(fn () => $action->execute($payment, 'txn-123'))
        ->toThrow(ValidationException::class, 'already been confirmed');
});

test('throws exception when confirming expired payment', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Expired,
    ]);

    $action = new ConfirmPayment;

    expect(fn () => $action->execute($payment))
        ->toThrow(ValidationException::class, 'Cannot confirm expired payment');
});

test('throws exception when confirming cancelled payment', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Cancelled,
    ]);

    $action = new ConfirmPayment;

    expect(fn () => $action->execute($payment))
        ->toThrow(ValidationException::class, 'Cannot confirm cancelled payment');
});

test('throws exception when payment has expired time-wise', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Pending,
        'expires_at' => now()->subHours(1), // Expired 1 hour ago
    ]);

    $action = new ConfirmPayment;

    expect(fn () => $action->execute($payment))
        ->toThrow(ValidationException::class, 'Payment has expired');
});
