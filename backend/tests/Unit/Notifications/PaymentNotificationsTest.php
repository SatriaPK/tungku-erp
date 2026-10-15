<?php

use App\Actions\Invoice\IssueInvoice;
use App\Actions\Payment\ConfirmPayment;
use App\Actions\Payment\CreatePayment;
use App\Actions\Payment\ExpirePayment;
use App\Actions\Payment\ProcessRefund;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentCreatedNotification;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\RefundProcessedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->client = User::factory()->client()->create();
    $this->project = Project::factory()->create(['client_id' => $this->client->id]);

    $this->payment = Payment::factory()->pending()->create([
        'project_id' => $this->project->id,
        'amount' => 500000,
        'order_id' => 'PAY-20261009-TEST',
    ]);

    $this->invoice = Invoice::factory()->create([
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
        'status' => 'draft',
    ]);
});

test('payment created notification can be rendered and has correct mail content', function () {
    $notification = new PaymentCreatedNotification($this->payment);
    $mail = $notification->toMail($this->client);

    expect($mail->subject)->toBe('Payment Request for '.$this->payment->order_id)
        ->and($mail->view)->toBe('emails.payment-created')
        ->and($mail->viewData['payment']->id)->toBe($this->payment->id);

    $array = $notification->toArray($this->client);
    expect($array['type'])->toBe('payment_created')
        ->and($array['amount'])->toBe($this->payment->amount);
});

test('payment confirmed notification can be rendered and has correct mail content', function () {
    $notification = new PaymentConfirmedNotification($this->payment);
    $mail = $notification->toMail($this->client);

    expect($mail->subject)->toBe('Payment Received - Receipt for '.$this->payment->order_id)
        ->and($mail->view)->toBe('emails.payment-confirmed')
        ->and($mail->viewData['payment']->id)->toBe($this->payment->id);

    $array = $notification->toArray($this->client);
    expect($array['type'])->toBe('payment_confirmed')
        ->and($array['amount'])->toBe($this->payment->amount);
});

test('payment failed notification does not send mail and has database array', function () {
    $notification = new PaymentFailedNotification($this->payment);

    $channels = $notification->via($this->client);
    expect($channels)->toBe(['database'])
        ->not->toContain('mail');

    $array = $notification->toArray($this->client);
    expect($array['type'])->toBe('payment_failed')
        ->and($array['amount'])->toBe($this->payment->amount);
});

test('refund processed notification can be rendered and has correct mail content', function () {
    $notification = new RefundProcessedNotification($this->payment);
    $mail = $notification->toMail($this->client);

    expect($mail->subject)->toBe('Refund Processed for '.$this->payment->order_id)
        ->and($mail->view)->toBe('emails.refund-processed')
        ->and($mail->viewData['payment']->id)->toBe($this->payment->id);

    $array = $notification->toArray($this->client);
    expect($array['type'])->toBe('refund_processed')
        ->and($array['amount'])->toBe($this->payment->amount);
});

test('invoice issued notification can be rendered and has correct mail content', function () {
    $notification = new InvoiceIssuedNotification($this->invoice);
    $mail = $notification->toMail($this->client);

    expect($mail->subject)->toBe('Invoice Issued: '.$this->invoice->invoice_number)
        ->and($mail->view)->toBe('emails.invoice-issued')
        ->and($mail->viewData['invoice']->id)->toBe($this->invoice->id);

    $array = $notification->toArray($this->client);
    expect($array['type'])->toBe('invoice_issued')
        ->and($array['invoice_number'])->toBe($this->invoice->invoice_number);
});

test('create payment action triggers notification', function () {
    $action = app(CreatePayment::class);
    $action->execute($this->project, PaymentType::Po, 500000, 'cash');

    Notification::assertSentTo(
        [$this->client],
        PaymentCreatedNotification::class
    );
});

test('confirm payment action triggers notification', function () {
    $action = app(ConfirmPayment::class);
    $action->execute($this->payment);

    Notification::assertSentTo(
        [$this->client],
        PaymentConfirmedNotification::class
    );
});

test('expire payment action triggers failed notification', function () {
    $this->payment->update(['expires_at' => now()->subDay()]);
    $action = app(ExpirePayment::class);
    $action->execute($this->payment);

    Notification::assertSentTo(
        [$this->client],
        PaymentFailedNotification::class
    );
});

test('process refund action triggers notification', function () {
    // Need to set as Paid before refund
    DB::table('payments')->where('id', $this->payment->id)->update([
        'status' => PaymentStatus::Paid->value,
        'paid_at' => now(),
        'provider' => 'cash', // Let use cash to avoid actual gateway exception for simpler testing
    ]);

    $action = app(ProcessRefund::class);
    $action->execute($this->payment->fresh());

    Notification::assertSentTo(
        [$this->client],
        RefundProcessedNotification::class
    );
});

test('issue invoice action triggers notification', function () {
    $this->invoice->items()->create([
        'description' => 'Test Item',
        'quantity' => 1,
        'unit_price' => 500000,
        'total' => 500000,
        'subtotal' => 500000,
    ]);

    $action = app(IssueInvoice::class);
    $action->execute($this->invoice);

    Notification::assertSentTo(
        [$this->client],
        InvoiceIssuedNotification::class
    );
});
test('payment created notification implements should queue', function () {
    $notification = new PaymentCreatedNotification($this->payment);
    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->tries)->toBe(3);
});

test('payment confirmed notification implements should queue', function () {
    $notification = new PaymentConfirmedNotification($this->payment);
    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->tries)->toBe(3);
});

test('payment failed notification implements should queue', function () {
    $notification = new PaymentFailedNotification($this->payment);
    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->tries)->toBe(3);
});

test('refund processed notification implements should queue', function () {
    $notification = new RefundProcessedNotification($this->payment);
    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->tries)->toBe(3);
});

test('invoice issued notification implements should queue', function () {
    $notification = new InvoiceIssuedNotification($this->invoice);
    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->tries)->toBe(3);
});
