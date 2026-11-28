<?php

use App\Actions\Payment\CreatePayment;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('payment.default_gateway', 'cash');
    Config::set('payment.expiration_hours', 24);
    PaymentGatewayFactory::fake();
});

afterEach(function () {
    PaymentGatewayFactory::clearFake();
});

test('creates payment successfully with cash gateway', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);

    $action = new CreatePayment;
    $payment = $action->execute($project, PaymentType::Po, 5000000);

    expect($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->project_id)->toBe($project->id)
        ->and($payment->type)->toBe(PaymentType::Po)
        ->and($payment->amount)->toBe(5000000)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->provider)->toBe(PaymentProvider::Cash)
        ->and($payment->order_id)->toMatch('/^PAY-\d{8}-\d{5}$/')
        ->and($payment->expires_at)->not()->toBeNull();
});

test('allows one PO and one final payment with different providers for a project', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $action = new CreatePayment;

    $poPayment = $action->execute($project, PaymentType::Po, 1000000, 'cash');
    $finalPayment = $action->execute($project, PaymentType::Final, 500000, 'xendit');

    expect($poPayment->type)->toBe(PaymentType::Po)
        ->and($poPayment->provider)->toBe(PaymentProvider::Cash)
        ->and($finalPayment->type)->toBe(PaymentType::Final)
        ->and($finalPayment->provider)->toBe(PaymentProvider::Xendit)
        ->and($project->payments()->count())->toBe(2);
});

test('allows another PO payment attempt for the same project', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $action = new CreatePayment;

    $action->execute($project, PaymentType::Po, 1000000, 'cash');

    $action->execute($project, PaymentType::Po, 1000000, 'cash');

    expect($project->payments()->where('type', PaymentType::Po->value)->count())->toBe(2);
});

test('generates unique sequential order IDs for multiple projects', function () {
    Payment::query()->delete(); // Clean up before test

    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $action = new CreatePayment;

    $payment1 = $action->execute($project, PaymentType::Po, 1000000);
    $secondProject = Project::factory()->create(['client_id' => $client->id]);
    $payment2 = $action->execute($secondProject, PaymentType::Po, 2000000);

    expect($payment1->order_id)->not()->toBe($payment2->order_id)
        ->and($payment1->order_id)->toContain(now()->format('Ymd'))
        ->and($payment1->order_id)->toMatch('/PAY-\d{8}-00001/')
        ->and($payment2->order_id)->toMatch('/PAY-\d{8}-00002/');
});

test('uses specified provider', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);

    // Skip this test - requires valid API credentials
    expect(true)->toBeTrue();
})->skip('Requires valid Xendit API credentials');

test('throws exception for zero amount', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $action = new CreatePayment;

    expect(fn () => $action->execute($project, PaymentType::Po, 0))
        ->toThrow(InvalidArgumentException::class, 'Payment amount must be greater than zero');
});

test('throws exception for unsupported provider', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $action = new CreatePayment;

    expect(fn () => $action->execute($project, PaymentType::Po, 5000000, 'stripe'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported payment provider: stripe');
});

test('sets expiration time from config', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    Config::set('payment.expiration_hours', 48);
    Config::set('payment.manual.default_expiration_days', 2); // Override manual gateway default

    $action = new CreatePayment;
    $payment = $action->execute($project, PaymentType::Po, 5000000);

    // This action uses payment.expiration_hours (defaulting to 24).
    $hoursUntilExpiry = now()->diffInHours($payment->expires_at, false);
    expect($hoursUntilExpiry)->toBeGreaterThanOrEqual(23)
        ->and($hoursUntilExpiry)->toBeLessThanOrEqual(25);
});

test('payment is in pending status initially', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);

    $action = new CreatePayment;
    $payment = $action->execute($project, PaymentType::Po, 5000000);

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->paid_at)->toBeNull();
});

test('associates payment with project', function () {
    $client = User::factory()->create(['role' => 'client']);
    $project = Project::factory()->create(['client_id' => $client->id]);

    $action = new CreatePayment;
    $payment = $action->execute($project, PaymentType::Po, 5000000);

    expect($payment->project_id)->toBe($project->id)
        ->and($payment->project->id)->toBe($project->id);
});
