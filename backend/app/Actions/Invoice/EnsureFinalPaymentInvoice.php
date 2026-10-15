<?php

namespace App\Actions\Invoice;

use App\Enum\InvoiceStatus;
use App\Enum\PaymentType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EnsureFinalPaymentInvoice
{
    public function __construct(private InvoiceNumberGenerator $numberGenerator) {}

    public function execute(Payment $payment): Invoice
    {
        if ($payment->type !== PaymentType::Final || $payment->project_id === null || $payment->amount <= 0) {
            throw new InvalidArgumentException('A positive final payment associated with a project is required.');
        }

        return DB::transaction(function () use ($payment): Invoice {
            $payment = Payment::query()->lockForUpdate()->with('project.client')->findOrFail($payment->id);

            if ($payment->invoice_id !== null) {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
                $this->assertInvoiceMatchesPayment($invoice, $payment);

                return $invoice;
            }

            $invoice = Invoice::query()
                ->where('project_id', $payment->project_id)
                ->where('metadata->payment_type', PaymentType::Final->value)
                ->lockForUpdate()
                ->first();

            if ($invoice === null) {
                $item = InvoiceItem::calculateAmounts($payment->amount);
                $invoice = Invoice::query()->create([
                    'invoice_number' => $this->numberGenerator->generate(),
                    'client_id' => $payment->project->client_id,
                    'project_id' => $payment->project_id,
                    'status' => InvoiceStatus::Issued,
                    'subtotal' => $payment->amount,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total' => $payment->amount,
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->addDays(7)->toDateString(),
                    'metadata' => ['payment_type' => PaymentType::Final->value],
                ]);
                $invoice->items()->create([
                    'line_number' => 1,
                    'description' => 'Pelunasan Project '.$payment->project_id,
                    'unit_price' => $payment->amount,
                    'quantity' => 1,
                    ...$item,
                ]);
            } else {
                $this->assertInvoiceMatchesPayment($invoice, $payment);
            }

            $payment->update(['invoice_id' => $invoice->id]);

            return $invoice->fresh(['items', 'client', 'project']);
        });
    }

    private function assertInvoiceMatchesPayment(Invoice $invoice, Payment $payment): void
    {
        if ($invoice->project_id !== $payment->project_id || $invoice->total !== $payment->amount) {
            throw new InvalidArgumentException('Final payment invoice does not match the locked payment amount.');
        }
    }
}
