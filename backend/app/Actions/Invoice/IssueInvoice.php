<?php

namespace App\Actions\Invoice;

use App\Enum\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoiceIssuedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Issue an invoice - transition from Draft to Issued status.
 *
 * Usage:
 * $invoice = app(IssueInvoice::class)->execute($invoice);
 */
class IssueInvoice
{
    /**
     * Issue the invoice.
     *
     * @throws InvalidArgumentException
     */
    public function execute(Invoice $invoice): Invoice
    {
        // Validate invoice can be issued
        $this->validate($invoice);

        return DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => InvoiceStatus::Issued,
                'issue_date' => now(),
            ]);

            Log::info('Invoice issued', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'total' => $invoice->total,
            ]);

            // Notify client
            if ($invoice->client) {
                $invoice->client->notify(new InvoiceIssuedNotification($invoice->fresh()));
            }

            return $invoice->fresh(['items', 'client', 'project']);
        });
    }

    /**
     * Validate that invoice can be issued.
     *
     * @throws InvalidArgumentException
     */
    protected function validate(Invoice $invoice): void
    {
        // Must be in Draft status
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new InvalidArgumentException(
                "Invoice must be in Draft status to be issued. Current status: {$invoice->status->label()}"
            );
        }

        // Must have at least one item
        if ($invoice->items()->count() === 0) {
            throw new InvalidArgumentException('Invoice must have at least one item');
        }

        // Total must be greater than zero
        if ($invoice->total <= 0) {
            throw new InvalidArgumentException('Invoice total must be greater than zero');
        }

        // Must have a client
        if (! $invoice->client_id) {
            throw new InvalidArgumentException('Invoice must be associated with a client');
        }
    }
}
