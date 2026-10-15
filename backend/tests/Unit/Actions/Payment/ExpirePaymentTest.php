<?php

use App\Actions\Payment\ExpirePayment;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;

test('expires pending payment successfully', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Pending,
        'expires_at' => now()->subHours(1),
    ]);

    $action = new ExpirePayment;
    $result = $action->execute($payment);

    expect($result->status)->toBe(PaymentStatus::Expired);
});

test('does not expire payment that has not reached expiration', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Pending,
        'expires_at' => now()->addHours(1), // Still valid
    ]);

    $action = new ExpirePayment;
    $result = $action->execute($payment);

    expect($result->status)->toBe(PaymentStatus::Pending);
});

test('does not expire already paid payment', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Paid,
        'expires_at' => now()->subHours(1),
        'paid_at' => now(),
    ]);

    $action = new ExpirePayment;
    $result = $action->execute($payment);

    expect($result->status)->toBe(PaymentStatus::Paid);
});

test('expires multiple overdue payments', function () {
    Payment::query()->delete(); // Clean up before test

    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);

    Payment::factory()->count(3)->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Pending,
        'expires_at' => now()->subHours(2),
    ]);

    $action = new ExpirePayment;
    $count = $action->expireAllOverdue();

    expect($count)->toBe(3);
});

test('does not expire future payments in batch', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);

    Payment::factory()->create([
        'project_id' => $project->id,
        'status' => PaymentStatus::Pending,
        'expires_at' => now()->addHours(1), // Future
    ]);

    $action = new ExpirePayment;
    $count = $action->expireAllOverdue();

    expect($count)->toBe(0);
});
