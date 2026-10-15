<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Generate unique sequential invoice numbers per year.
 *
 * Format: INV-YYYY-XXXXX (e.g., INV-2026-00001)
 */
class InvoiceNumberGenerator
{
    /**
     * Generate the next invoice number for the current year.
     *
     * This method is thread-safe using database locks.
     */
    public function generate(?int $year = null): string
    {
        $year = $year ?? now()->year;

        return DB::transaction(function () use ($year) {
            // Get the last invoice number for this year with a lock
            $lastInvoice = Invoice::query()
                ->where('invoice_number', 'LIKE', "INV-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('invoice_number')
                ->first();

            if ($lastInvoice) {
                // Extract the sequence number and increment
                $lastNumber = (int) substr($lastInvoice->invoice_number, -5);
                $nextNumber = $lastNumber + 1;
            } else {
                // First invoice of the year
                $nextNumber = 1;
            }

            return sprintf('INV-%d-%05d', $year, $nextNumber);
        });
    }

    /**
     * Validate invoice number format.
     */
    public function isValid(string $invoiceNumber): bool
    {
        return (bool) preg_match('/^INV-\d{4}-\d{5}$/', $invoiceNumber);
    }

    /**
     * Extract year from invoice number.
     */
    public function extractYear(string $invoiceNumber): ?int
    {
        if (! $this->isValid($invoiceNumber)) {
            return null;
        }

        return (int) substr($invoiceNumber, 4, 4);
    }

    /**
     * Extract sequence number from invoice number.
     */
    public function extractSequence(string $invoiceNumber): ?int
    {
        if (! $this->isValid($invoiceNumber)) {
            return null;
        }

        return (int) substr($invoiceNumber, -5);
    }
}
