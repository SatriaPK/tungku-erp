<?php

use App\Actions\Payment\ConfirmManualPayment;
use App\Actions\Payment\ConfirmPayment;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Jobs\HandlePaymentWebhook;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Models\WebhookLog;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

function finalSettlementPayment(int $amount = 1000): array
{
    $client = User::factory()->client()->create();
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'status' => ProjectStatus::AwaitingFinalPayment,
        'price' => $amount,
        'po_amount' => 0,
        'final_payment_amount' => $amount,
    ]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'total' => $amount,
        'status' => InvoiceStatus::Issued,
    ]);
    $payment = Payment::factory()->create([
        'project_id' => $project->id,
        'invoice_id' => $invoice->id,
        'type' => PaymentType::Final,
        'amount' => $amount,
        'status' => PaymentStatus::Pending,
        'provider' => PaymentProvider::Xendit,
        'expires_at' => now()->addDay(),
    ]);

    return [$project, $payment, $invoice];
}

test('valid full final gateway payment settles project and invoice exactly once', function () {
    [$project, $payment, $invoice] = finalSettlementPayment();

    app(ConfirmPayment::class)->execute($payment, 'gateway-txn-final', 'credit_card');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($project->fresh()->status)->toBe(ProjectStatus::Completed)
        ->and($project->fresh()->completed_at)->not->toBeNull()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    expect(fn () => app(ConfirmPayment::class)->execute($payment->fresh(), 'duplicate-txn', 'credit_card'))
        ->toThrow(ValidationException::class);

    expect($project->fresh()->payments()->where('status', PaymentStatus::Paid->value)->count())->toBe(1);
});

test('final gateway confirmation rejects amount mismatch without marking payment or project paid', function () {
    [$project, $payment, $invoice] = finalSettlementPayment();
    expect(fn () => app(ConfirmPayment::class)->execute($payment, 'mismatch-txn', 'credit_card', ['gross_amount' => '900.00']))
        ->toThrow(InvalidWebhookSignatureException::class);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($project->fresh()->status)->toBe(ProjectStatus::AwaitingFinalPayment)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Issued);
});

test('late callback cannot settle an expired final payment', function () {
    [$project, $payment, $invoice] = finalSettlementPayment();
    $payment->update(['status' => PaymentStatus::Expired]);

    expect(fn () => app(ConfirmPayment::class)->execute($payment, 'late-txn', 'credit_card'))
        ->toThrow(ValidationException::class);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($project->fresh()->status)->toBe(ProjectStatus::AwaitingFinalPayment)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Issued);
});

test('manual cash confirmation uses the same full settlement effect', function () {
    [$project, $payment, $invoice] = finalSettlementPayment();
    $payment->update([
        'provider' => PaymentProvider::Cash,
        'status' => PaymentStatus::PendingManualConfirmation,
    ]);

    app(ConfirmManualPayment::class)->execute($payment, 'Cash received');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($project->fresh()->status)->toBe(ProjectStatus::Completed)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('manual final settlement stores the supplied instant in application time', function (string $input, string $stored) {
    [$project, $payment, $invoice] = finalSettlementPayment();
    $payment->update(['provider' => PaymentProvider::Cash]);
    $paidAt = new DateTime($input);
    $original = clone $paidAt;

    $result = app(ConfirmManualPayment::class)->execute($payment, 'Cash received', $paidAt);

    expect($paidAt)->toEqual($original);
    expect($result->paid_at->getTimestamp())->toBe($original->getTimestamp());
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid', 'paid_at' => $stored]);
    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid', 'paid_at' => $stored]);
    $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'completed', 'completed_at' => $stored]);
})->with([
    'before month boundary' => ['2026-09-30T16:59:59Z', '2026-09-30 23:59:59'],
    'UTC month start' => ['2026-09-30T17:00:00Z', '2026-10-01 00:00:00'],
    'equivalent local offset' => ['2026-10-01T00:00:00+07:00', '2026-10-01 00:00:00'],
    'timezone-less local' => ['2026-10-01 00:00:00', '2026-10-01 00:00:00'],
    'last second of month' => ['2026-10-31T16:59:59Z', '2026-10-31 23:59:59'],
    'next month start' => ['2026-10-31T17:00:00Z', '2026-11-01 00:00:00'],
]);

test('manager confirmation returns the normalized manual final payment instant', function () {
    $this->travelTo(Carbon::parse('2026-10-02 12:00:00'));
    [, $payment] = finalSettlementPayment();
    $payment->update(['provider' => PaymentProvider::Cash]);
    Sanctum::actingAs(User::factory()->manager()->create());

    $this->postJson("/api/v1/manager/payments/{$payment->id}/confirm", [
        'note' => 'Cash received',
        'paid_at' => '2026-09-30T17:00:00Z',
    ])->assertOk()->assertJsonPath('data.paid_at', '2026-10-01T00:00:00+07:00');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'paid_at' => '2026-10-01 00:00:00']);
});

test('webhook with a mismatched amount fails and does not complete final project', function () {
    [$project, $payment, $invoice] = finalSettlementPayment();
    $log = WebhookLog::create([
        'gateway' => 'xendit',
        'event_type' => 'settlement',
        'transaction_id' => 'mismatch-webhook-txn',
        'order_id' => $payment->order_id,
        'payload' => ['gross_amount' => 900],
        'headers' => [],
        'status' => 'received',
    ]);

    $job = new HandlePaymentWebhook($log, [
        'order_id' => $payment->order_id,
        'transaction_id' => 'mismatch-webhook-txn',
        'status' => PaymentStatus::Paid->value,
        'amount' => 900,
        'payment_method' => 'credit_card',
    ]);
    $job->handle();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($project->fresh()->status)->toBe(ProjectStatus::AwaitingFinalPayment)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Issued)
        ->and($log->fresh()->status)->toBe('failed');
});
