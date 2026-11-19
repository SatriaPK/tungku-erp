<?php

namespace App\Actions\Payment;

use App\Actions\Booking\CouponPaymentLifecycle;
use App\Actions\Notification\NotifyBusinessEvent;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InitiatePayment
{
    /**
     * Initiate a payment for an invoice.
     *
     * Creates a Payment record and obtains a payment URL from the gateway.
     *
     * @throws InvalidArgumentException
     */
    public function execute(Invoice $invoice, string $gateway = 'midtrans'): Payment
    {
        // Validate invoice state
        if ($invoice->status !== InvoiceStatus::Issued) {
            throw new InvalidArgumentException('Invoice must be in Issued status to initiate payment.');
        }

        if ($invoice->total <= 0) {
            throw new InvalidArgumentException('Invoice total must be greater than zero.');
        }

        // A retry may create a new attempt, but an active attempt or successful
        // payment always blocks another charge for this invoice.
        $hasActivePayment = $invoice->payments()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::PendingManualConfirmation->value])
            ->exists();
        $hasPaidPayment = $invoice->payments()->where('status', PaymentStatus::Paid->value)->exists();

        if ($hasActivePayment || $hasPaidPayment) {
            throw new InvalidArgumentException('Invoice already has an active payment.');
        }

        if (! PaymentGatewayFactory::supports($gateway)) {
            throw new InvalidArgumentException("Unsupported payment gateway: {$gateway}");
        }

        if ($invoice->project_id === null && $invoice->recording_session_id === null) {
            throw new InvalidArgumentException('Invoice must be associated with a project or recording session.');
        }

        return DB::transaction(function () use ($invoice, $gateway) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== InvoiceStatus::Issued || $invoice->payments()->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::PendingManualConfirmation->value, PaymentStatus::Paid->value])->exists()) {
                throw new InvalidArgumentException('Invoice already has an active or successful payment.');
            }
            // Generate unique order ID
            $orderId = sprintf('INV-%s-%05d-%s', now()->format('Ymd'), $invoice->id, Str::upper(Str::random(8)));

            // Create payment record
            $payment = Payment::create([
                'project_id' => $invoice->project_id ?? $this->resolveProjectId($invoice),
                'invoice_id' => $invoice->id,
                'type' => $this->resolvePaymentType($invoice),
                'amount' => $invoice->total,
                'status' => PaymentStatus::Pending,
                'provider' => $this->mapGatewayToProvider($gateway),
                'order_id' => $orderId,
                'expires_at' => now()->addHours(config('payment.expiration_hours', 24)),
            ]);

            app(CouponPaymentLifecycle::class)->reserve($payment);

            // Load relationships needed by gateway
            $payment->load('project.client');

            // Create charge via gateway
            $gatewayService = PaymentGatewayFactory::make($gateway);
            $chargeResult = $gatewayService->createCharge($payment);

            // Update payment with gateway response
            $payment->update([
                'gateway_charge_id' => $chargeResult['charge_id'],
                'payment_url' => $chargeResult['payment_url'],
                'snap_token' => $chargeResult['snap_token'],
                'expires_at' => $chargeResult['expires_at'],
            ]);

            CouponRedemption::where('payment_id', $payment->id)->where('status', 'reserved')
                ->update(['reserved_until' => $payment->expires_at]);

            // Link payment to recording session if applicable
            if ($invoice->recordingSession) {
                $invoice->recordingSession->update(['payment_id' => $payment->id]);
            }

            Log::info('Payment initiated', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'gateway' => $gateway,
                'order_id' => $orderId,
            ]);

            $payment = $payment->refresh();
            app(NotifyBusinessEvent::class)->paymentCreated($payment);

            return $payment;
        });
    }

    private function resolvePaymentType(Invoice $invoice): PaymentType
    {
        $existingType = $invoice->payments()->latest('id')->value('type');

        if ($existingType instanceof PaymentType) {
            return $existingType;
        }

        return $existingType === null ? PaymentType::Po : PaymentType::from($existingType);
    }

    /**
     * If invoice has no project, use a fallback (e.g., from recording session's project or create a stub).
     */
    private function resolveProjectId(Invoice $invoice): ?int
    {
        // If invoice is tied to a recording session that has a project
        return $invoice->recordingSession?->project_id;
    }

    /**
     * Map gateway name to PaymentProvider enum.
     */
    private function mapGatewayToProvider(string $gateway): PaymentProvider
    {
        return match ($gateway) {
            'midtrans' => PaymentProvider::Midtrans,
            'xendit' => PaymentProvider::Xendit,
            'manual', 'cash' => PaymentProvider::Cash,
            default => throw new InvalidArgumentException("Cannot map gateway to provider: {$gateway}"),
        };
    }
}
