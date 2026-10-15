<?php

use App\Contracts\PaymentGatewayInterface;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;

beforeEach(function () {
    $this->client = User::factory()->client()->create();
    $this->project = Project::factory()->create(['client_id' => $this->client->id]);

    $this->xenditPayment = Payment::factory()->pending()->xendit()->create([
        'project_id' => $this->project->id,
        'amount' => 500000,
        'provider_transaction_id' => 'xen_123',
        'order_id' => 'PAY-XEN-TEST',
    ]);

    $this->midtransPayment = Payment::factory()->pending()->midtrans()->create([
        'project_id' => $this->project->id,
        'amount' => 750000,
        'provider_transaction_id' => 'mid_123',
        'order_id' => 'PAY-MID-TEST',
    ]);
});

afterEach(function () {
    PaymentGatewayFactory::clearFake();
});

test('sync payment status checks gateway and updates to paid', function () {
    $mockGateway = Mockery::mock(PaymentGatewayInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('getChargeStatus')->with('xen_123')->once()->andReturn('paid');
        $mock->shouldReceive('getChargeStatus')->with('mid_123')->once()->andReturn('pending');
    });

    PaymentGatewayFactory::fake($mockGateway);
    Artisan::call('payment:sync');

    expect(Artisan::output())->toContain('Synced 2 payments.');
    expect($this->xenditPayment->fresh()->status)->toBe(PaymentStatus::Paid);
    expect($this->midtransPayment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('sync payment status command works with dry run', function () {
    Artisan::call('payment:sync', ['--dry-run' => true]);
    $output = Artisan::output();
    expect($output)->toContain('Syncing payment statuses (DRY RUN)')->and($output)->toContain('Synced 2 payments.');
    expect($this->xenditPayment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('sync payment status filters by gateway', function () {
    $mockGateway = Mockery::mock(PaymentGatewayInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('getChargeStatus')->with('mid_123')->once()->andReturn('expired');
    });

    PaymentGatewayFactory::fake($mockGateway);
    Artisan::call('payment:sync', ['--gateway' => 'midtrans']);

    expect(Artisan::output())->toContain('Synced 1 payments.');
    expect($this->xenditPayment->fresh()->status)->toBe(PaymentStatus::Pending);
    expect($this->midtransPayment->fresh()->status)->toBe(PaymentStatus::Expired);
});

test('sync payment prints info dynamically with verbose flag', function () {
    $mockGateway = Mockery::mock(PaymentGatewayInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('getChargeStatus')->with('mid_123')->once()->andReturn('failed');
    });

    PaymentGatewayFactory::fake($mockGateway);
    Artisan::call('payment:sync', ['--gateway' => 'midtrans', '-v' => true]);

    expect(Artisan::output())->toContain('Synced and marked payment PAY-MID-TEST as failed');
    expect($this->midtransPayment->fresh()->status)->toBe(PaymentStatus::Failed);
});

test('sync payment status does nothing if no pending payments', function () {
    Payment::query()->update(['status' => PaymentStatus::Paid->value]);
    Artisan::call('payment:sync');
    expect(Artisan::output())->toContain('No pending payments to sync.');
});

test('sync payment ignores already paid or failed payments locally', function () {
    $this->xenditPayment->update(['status' => PaymentStatus::Paid]);
    $this->midtransPayment->update(['status' => PaymentStatus::Failed]);
    Artisan::call('payment:sync');
    expect(Artisan::output())->toContain('No pending payments to sync.');
});

test('sync payment status handles gateway exception gracefully', function () {
    $mockGateway = Mockery::mock(PaymentGatewayInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('getChargeStatus')->andThrow(new Exception('Gateway timeout simulator'));
    });

    PaymentGatewayFactory::fake($mockGateway);
    Artisan::call('payment:sync', ['-v' => true]);

    expect(Artisan::output())->toContain('Failed to sync payment PAY-XEN-TEST: Gateway timeout simulator');
    expect($this->xenditPayment->fresh()->status)->toBe(PaymentStatus::Pending);
});
test('sync payment ignores manual cash payments pending review', function () {
    $cashPayment = Payment::factory()->pending()->create([
        'project_id' => $this->project->id,
        'amount' => 500000,
        'provider' => 'cash',
        'provider_transaction_id' => 'cash-bank-transfer',
        'status' => PaymentStatus::PendingManualConfirmation,
        'order_id' => 'PAY-CASH-TEST',
    ]);

    Artisan::call('payment:sync');
    expect($cashPayment->fresh()->status)->toBe(PaymentStatus::PendingManualConfirmation);
});

test('sync payment returns success early if query matches null provider_transaction_id', function () {
    Payment::factory()->pending()->create([
        'project_id' => $this->project->id,
        'amount' => 500000,
        'provider' => 'midtrans',
        'provider_transaction_id' => null,
    ]);

    Artisan::call('payment:sync', ['--gateway' => 'midtrans', '--limit' => 5]);
    expect(Artisan::output())->toContain('Synced 1 payments.');
});

test('sync payment command handles batch limit correctly', function () {
    for ($i = 0; $i < 5; $i++) {
        Payment::factory()->pending()->midtrans()->create([
            'project_id' => $this->project->id,
            'amount' => 50000,
            'provider_transaction_id' => 'mid_batch_'.$i,
        ]);
    }

    $mockGateway = Mockery::mock(PaymentGatewayInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('getChargeStatus')->andReturn('pending');
    });

    PaymentGatewayFactory::fake($mockGateway);
    Artisan::call('payment:sync', ['--gateway' => 'midtrans', '--limit' => 3]);

    expect(Artisan::output())->toContain('Synced 3 payments.');
});

test('sync payment ignores ambiguous other states returned by gateway', function () {
    $mockGateway = Mockery::mock(PaymentGatewayInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('getChargeStatus')->andReturn('waiting_payment');
    });

    PaymentGatewayFactory::fake($mockGateway);
    Artisan::call('payment:sync', ['--gateway' => 'xendit']);

    // waiting_payment tidak merubah apa-apa
    expect($this->xenditPayment->fresh()->status)->toBe(PaymentStatus::Pending);
});
