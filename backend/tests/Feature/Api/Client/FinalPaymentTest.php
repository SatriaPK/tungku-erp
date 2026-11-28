<?php

use App\Contracts\PaymentGatewayInterface;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    PaymentGatewayFactory::fake();
});

afterEach(function () {
    PaymentGatewayFactory::clearFake();
});

function finalPaymentProject(User $client, int $amount = 125000): array
{
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'status' => ProjectStatus::AwaitingFinalPayment,
        'final_payment_amount' => $amount,
    ]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'type' => PaymentType::Final,
        'amount' => $amount,
        'status' => PaymentStatus::Pending,
        'provider' => PaymentProvider::Xendit,
    ]);

    return [$project, $payment];
}

test('initiates the owned final payment using the locked amount and matching invoice', function () {
    $client = User::factory()->client()->create();
    [, $payment] = finalPaymentProject($client);
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/client/payments/{$payment->id}/final/initiate", ['provider' => 'xendit'])
        ->assertOk()
        ->assertJsonPath('data.type', 'final')
        ->assertJsonPath('data.amount', 125000)
        ->assertJsonPath('data.invoice.total', 125000)
        ->assertJsonPath('data.status', 'pending');

    expect(Invoice::query()->count())->toBe(1)
        ->and($payment->fresh()->invoice_id)->not->toBeNull();
});

test('creates a pending manual cash final payment without a gateway charge', function () {
    $client = User::factory()->client()->create();
    [, $payment] = finalPaymentProject($client);
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/client/payments/{$payment->id}/final/initiate", ['provider' => 'cash'])
        ->assertOk()
        ->assertJsonPath('data.provider', 'cash')
        ->assertJsonPath('data.status', 'pending_manual_confirmation')
        ->assertJsonPath('data.payment_url', null);
});

test('does not allow a client to initiate someone elses final payment', function () {
    $client = User::factory()->client()->create();
    $otherClient = User::factory()->client()->create();
    [, $payment] = finalPaymentProject($otherClient);
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/client/payments/{$payment->id}/final/initiate", ['provider' => 'cash'])
        ->assertForbidden();
});

test('does not expose payment start to non-client roles', function () {
    $manager = User::factory()->manager()->create();
    [, $payment] = finalPaymentProject(User::factory()->client()->create());
    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/client/payments/{$payment->id}/final/initiate", ['provider' => 'cash'])
        ->assertForbidden();
});

test('rejects unsupported providers without creating an invoice', function () {
    $client = User::factory()->client()->create();
    [, $payment] = finalPaymentProject($client);
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/client/payments/{$payment->id}/final/initiate", ['provider' => 'manual'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['provider']);

    expect(Invoice::query()->count())->toBe(0)
        ->and($payment->fresh()->provider)->toBe(PaymentProvider::Xendit);
});

test('keeps the invoice issued when gateway charging fails', function () {
    $client = User::factory()->client()->create();
    [, $payment] = finalPaymentProject($client);
    PaymentGatewayFactory::fake(new class implements PaymentGatewayInterface
    {
        public function getName(): string
        {
            return 'failing';
        }

        public function createCharge(Payment $payment): array
        {
            throw new PaymentGatewayException('Gateway unavailable.');
        }

        public function getChargeStatus(string $chargeId): string
        {
            return 'pending';
        }

        public function processRefund(Payment $payment, int $amount, string $reason): array
        {
            return [
                'refund_id' => 'unused',
                'status' => 'success',
                'refunded_at' => now(),
            ];
        }

        public function verifyWebhookSignature(Request $request): bool
        {
            return false;
        }

        public function parseWebhookPayload(Request $request): array
        {
            return [
                'order_id' => 'unused',
                'transaction_id' => 'unused',
                'status' => 'pending',
                'amount' => 0,
                'payment_method' => null,
                'paid_at' => null,
            ];
        }
    });
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/client/payments/{$payment->id}/final/initiate", ['provider' => 'xendit'])
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.invoice.status', 'issued');

    expect($payment->fresh()->invoice_id)->not->toBeNull()
        ->and($payment->fresh()->invoice->status->value)->toBe('issued');
});
