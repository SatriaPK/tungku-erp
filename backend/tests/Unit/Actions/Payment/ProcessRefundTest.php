<?php

use App\Actions\Payment\ProcessRefund;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Config::set('payment.default_gateway', 'manual');
    Config::set('payment.refund_window_days', 90);
});

test('processes full refund successfully', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Paid,
        'amount' => 5000000,
        'provider' => PaymentProvider::Cash,
        'paid_at' => now(),
    ]);

    $action = new ProcessRefund;
    $result = $action->execute($payment, null, 'Customer requested');

    expect($result->status)->toBe(PaymentStatus::Refunded)
        ->and($result->raw_payload['refund']['amount'])->toBe(5000000)
        ->and($result->raw_payload['refund']['reason'])->toBe('Customer requested');
});

test('processes partial refund successfully', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Paid,
        'amount' => 5000000,
        'provider' => PaymentProvider::Cash,
        'paid_at' => now(),
    ]);

    $action = new ProcessRefund;
    $result = $action->execute($payment, 2500000, 'Partial refund');

    expect($result->status)->toBe(PaymentStatus::Refunded)
        ->and($result->raw_payload['refund']['amount'])->toBe(2500000);
});

test('throws exception when refunding non-paid payment', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Pending,
    ]);

    $action = new ProcessRefund;

    expect(fn () => $action->execute($payment, null, 'Test'))
        ->toThrow(ValidationException::class, 'Only paid payments can be refunded');
});

test('throws exception for zero refund amount', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $action = new ProcessRefund;

    expect(fn () => $action->execute($payment, 0, 'Test'))
        ->toThrow(ValidationException::class, 'Refund amount must be greater than zero');
});

test('throws exception when refund amount exceeds payment amount', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Paid,
        'amount' => 5000000,
        'paid_at' => now(),
    ]);

    $action = new ProcessRefund;

    expect(fn () => $action->execute($payment, 6000000, 'Test'))
        ->toThrow(ValidationException::class, 'cannot exceed payment amount');
});

test('throws exception when refund window has expired', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Paid,
        'paid_at' => now()->subDays(91), // 91 days ago
    ]);

    $action = new ProcessRefund;

    expect(fn () => $action->execute($payment, null, 'Test'))
        ->toThrow(ValidationException::class, 'Refund window has expired');
});
