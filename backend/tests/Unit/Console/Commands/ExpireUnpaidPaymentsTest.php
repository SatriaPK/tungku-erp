<?php

use App\Actions\Payment\ExpirePayment;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->client = User::factory()->client()->create();
    $this->project = Project::factory()->create(['client_id' => $this->client->id]);

    // Create an expired testing payment
    $this->expiredPayment = Payment::factory()->pending()->create([
        'project_id' => $this->project->id,
        'amount' => 500000,
        'expires_at' => now()->subDay(),
        'order_id' => 'PAY-EXPIRED-TEST',
    ]);

    // Create an active pending testing payment
    $this->activePayment = Payment::factory()->pending()->create([
        'project_id' => $this->project->id,
        'amount' => 500000,
        'expires_at' => now()->addDay(),
        'order_id' => 'PAY-ACTIVE-TEST',
    ]);
});

test('expire unpaid payments command executes correctly', function () {
    Artisan::call('payment:expire');

    $output = Artisan::output();

    expect($output)->toContain('Checking for expired payments')
        ->and($output)->toContain('Processed 1 expired payments.');

    expect($this->expiredPayment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($this->activePayment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('expire unpaid payments command respects dry run', function () {
    // Reset to test dry-run
    $this->expiredPayment->update(['status' => PaymentStatus::Pending]);

    Artisan::call('payment:expire', ['--dry-run' => true]);

    $output = Artisan::output();

    expect($output)->toContain('Checking for expired payments (DRY RUN)')
        ->and($output)->toContain('Processed 1 expired payments.');

    // Status should not change
    expect($this->expiredPayment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('expire unpaid payments command limits query', function () {
    Payment::factory()->pending()->count(5)->create([
        'project_id' => $this->project->id,
        'amount' => 100000,
        'expires_at' => now()->subDay(),
    ]);

    Artisan::call('payment:expire', ['--limit' => 3]);

    $output = Artisan::output();
    expect($output)->toContain('Processed 3 expired payments.');
});

test('expire unpaid payments does nothing if no expired payments exist', function () {
    // Clear all expired payments
    Payment::where('status', PaymentStatus::Pending)->update(['expires_at' => now()->addDays(5)]);

    Artisan::call('payment:expire');

    $output = Artisan::output();
    expect($output)->toContain('No expired payments found.');
});

test('expire unpaid payments executes successfully with verbosity', function () {
    Artisan::call('payment:expire', ['-v' => true]);

    $output = Artisan::output();

    expect($output)->toContain('Expired payment: PAY-EXPIRED-TEST');
    expect($this->expiredPayment->fresh()->status)->toBe(PaymentStatus::Expired);
});

test('expire command works securely if a payment is already confirmed or failed', function () {
    // Should not select failed/paid payments
    $this->expiredPayment->update(['status' => PaymentStatus::Failed]);

    Artisan::call('payment:expire');

    expect(Artisan::output())->toContain('No expired payments found.');
});

test('expire command with custom mock time travel', function () {
    $payment = Payment::factory()->pending()->create([
        'project_id' => $this->project->id,
        'amount' => 1000,
        'expires_at' => now()->addMinutes(10),
    ]);

    // Initially not expired
    Artisan::call('payment:expire', ['--limit' => 1]);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);

    // Travel forward in time to simulate expiration
    $this->travel(15)->minutes();

    Artisan::call('payment:expire');
    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired);

    // reset back
    $this->travelBack();
});

test('expire unpaid payments handles exception and continues', function () {
    // We can use a mock to throw an exception to verify it handles gracefully
    $action = Mockery::mock(ExpirePayment::class);
    $action->shouldReceive('execute')->andThrow(new Exception('Mocked Failure'));

    $this->app->instance(ExpirePayment::class, $action);

    Artisan::call('payment:expire', ['-v' => true]);

    $output = Artisan::output();
    expect($output)->toContain('Failed to expire payment PAY-EXPIRED-TEST: Mocked Failure');
});
