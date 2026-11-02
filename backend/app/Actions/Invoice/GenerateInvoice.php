<?php

namespace App\Actions\Invoice;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Enum\InvoiceStatus;
use App\Enum\StageType;
use App\Models\BasePrice;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\RecordingSession;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Generate invoices from payments, projects, or recording sessions.
 *
 * This action handles the creation of invoices with proper item breakdown,
 * calculations, and database consistency through transactions.
 */
class GenerateInvoice
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        protected InvoiceNumberGenerator $numberGenerator
    ) {}

    /**
     * Generate an invoice from a payment.
     *
     * @throws InvalidArgumentException
     */
    public function fromPayment(Payment $payment): Invoice
    {
        // Validate that payment has a project
        if (! $payment->project_id) {
            throw new InvalidArgumentException('Payment must be associated with a project to generate an invoice.');
        }

        // Load required relationships
        $payment->load(['project.client']);

        return $this->generate(
            clientId: $payment->project->client_id,
            projectId: $payment->project_id,
            items: $this->buildItemsFromPayment($payment),
            sourceType: Payment::class,
            sourceId: $payment->id
        );
    }

    /**
     * Generate an invoice from a project.
     *
     * @throws InvalidArgumentException
     */
    public function fromProject(Project $project): Invoice
    {
        // Load required relationships
        $project->load('client');

        return $this->generate(
            clientId: $project->client_id,
            projectId: $project->id,
            items: $this->buildItemsFromProject($project),
            sourceType: Project::class,
            sourceId: $project->id
        );
    }

    /**
     * Generate an invoice from a recording session.
     *
     * @throws InvalidArgumentException
     */
    public function fromRecordingSession(RecordingSession $session): Invoice
    {
        // Load required relationships
        $session->load(['project.client']);

        // Since recording session might not always have a project at booking time, fallback to client_id
        $clientId = $session->client_id ?? $session->project->client_id;

        if (! $clientId) {
            throw new InvalidArgumentException('Cannot determine client for the recording session.');
        }

        return $this->generate(
            clientId: $clientId,
            projectId: $session->project_id,
            items: $this->buildItemsFromSession($session),
            sourceType: RecordingSession::class,
            sourceId: $session->id
        );
    }

    /**
     * Generate an invoice with the given data.
     *
     * @throws InvalidArgumentException
     */
    protected function generate(
        int $clientId,
        ?int $projectId,
        array $items,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): Invoice {
        // Validate that we have at least one item
        if (empty($items)) {
            throw new InvalidArgumentException('Invoice must have at least one item.');
        }

        return DB::transaction(function () use ($clientId, $projectId, $items, $sourceType, $sourceId) {
            // Calculate amounts for each item
            $items = array_map(fn ($item) => $this->calculateItemAmounts($item), $items);

            // Calculate totals
            $totals = $this->calculateTotals($items);

            // Generate unique invoice number
            $invoiceNumber = $this->numberGenerator->generate();

            // Calculate due date
            $dueDays = config('invoices.default_due_days', 7);
            $dueDate = now()->addDays($dueDays);

            // Create the invoice
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'client_id' => $clientId,
                'project_id' => $projectId,
                'status' => InvoiceStatus::Draft,
                'issue_date' => now(),
                'due_date' => $dueDate,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'discount_amount' => $totals['discount_amount'],
                'total' => $totals['total'],
            ]);

            // Save source to correct column if present
            if ($sourceType === RecordingSession::class) {
                $invoice->recording_session_id = $sourceId;
            } elseif ($sourceType === Project::class) {
                $invoice->project_id = $sourceId;
            } // Payment has invoice_id mapped back instead
            $invoice->save();
            app(RecordBusinessChange::class)->handle($invoice, 'invoice.created', [], AuditActor::system(self::class));

            // Create invoice items with line numbers
            foreach ($items as $index => $itemData) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'line_number' => $index + 1,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_rate' => $itemData['tax_rate'] ?? 0,
                    'discount_rate' => $itemData['discount_rate'] ?? 0,
                    'subtotal' => $itemData['subtotal'],
                    'tax_amount' => $itemData['tax_amount'],
                    'discount_amount' => $itemData['discount_amount'],
                    'total' => $itemData['total'],
                ]);
            }

            // Log the invoice generation
            Log::info('Invoice generated', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceNumber,
                'client_id' => $clientId,
                'project_id' => $projectId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'total' => $totals['total'],
            ]);

            // Return fresh invoice with relationships
            return $invoice->fresh(['items', 'client', 'project']);
        });
    }

    /**
     * Build invoice items from a payment.
     */
    protected function buildItemsFromPayment(Payment $payment): array
    {
        return [
            [
                'description' => 'Payment for Project',
                'quantity' => 1,
                'unit_price' => $payment->amount,
                'tax_rate' => 0,
                'discount_rate' => 0,
            ],
        ];
    }

    /**
     * Build invoice items from a project.
     */
    protected function buildItemsFromProject(Project $project): array
    {
        if ($project->quotation_id !== null) {
            return [[
                'description' => $project->name,
                'quantity' => 1,
                'unit_price' => $project->price,
                'tax_rate' => 0,
                'discount_rate' => 0,
            ]];
        }

        return [
            [
                'description' => $project->name ?? 'Project Service',
                'quantity' => 1,
                'unit_price' => $project->price ?? 0,
                'tax_rate' => 0,
                'discount_rate' => 0,
            ],
        ];
    }

    /**
     * Build invoice items from a recording session.
     */
    protected function buildItemsFromSession(RecordingSession $session): array
    {
        $description = sprintf(
            'Recording Session - %s to %s',
            $session->start_at->format('Y-m-d H:i'),
            $session->end_at->format('H:i')
        );

        $unitPrice = 0;
        $quantity = 1;

        $session->loadMissing('package');

        if ($session->package) {
            $unitPrice = $session->package->price;
        } else {
            // Default hourly fallback using BasePrice for Recording
            $hourlyRate = BasePrice::where('stage', StageType::Recording->value)->value('price') ?? 0;

            // Determine hours, minimum 1.
            $quantity = max(1, $session->start_at->diffInMinutes($session->end_at) / 60);

            $unitPrice = $hourlyRate;
        }

        return [
            [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => 0,
                'discount_rate' => 0,
            ],
        ];
    }

    /**
     * Calculate amounts for a single invoice item.
     *
     * All amounts are in integer cents (IDR without decimals).
     */
    protected function calculateItemAmounts(array $item): array
    {
        $quantity = $item['quantity'] ?? 1;
        $unitPrice = (int) ($item['unit_price'] ?? 0);
        $taxRate = $item['tax_rate'] ?? 0;
        $discountRate = $item['discount_rate'] ?? 0;

        // Calculate subtotal (integer)
        $subtotal = $quantity * $unitPrice;

        // Calculate discount amount (integer)
        $discountAmount = (int) round($subtotal * ($discountRate / 100));

        // Calculate amount after discount
        $amountAfterDiscount = $subtotal - $discountAmount;

        // Calculate tax amount on discounted amount (integer)
        $taxAmount = (int) round($amountAfterDiscount * ($taxRate / 100));

        // Calculate total
        $total = $amountAfterDiscount + $taxAmount;

        return array_merge($item, [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
        ]);
    }

    /**
     * Calculate totals from invoice items.
     *
     * All amounts are in integer cents (IDR without decimals).
     */
    protected function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $taxAmount = 0;
        $discountAmount = 0;
        $total = 0;

        foreach ($items as $item) {
            $subtotal += $item['subtotal'];
            $taxAmount += $item['tax_amount'];
            $discountAmount += $item['discount_amount'];
            $total += $item['total'];
        }

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
        ];
    }
}
