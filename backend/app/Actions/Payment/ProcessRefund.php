<?php

namespace App\Actions\Payment;

use App\Enum\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\RecordingSession;
use App\Notifications\RefundProcessedNotification;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Process payment refund through payment gateway.
 *
 * Usage:
 * app(ProcessRefund::class)->execute($payment, $amount, $reason);
 */
class ProcessRefund
{
    /**
     * Process refund for a paid payment.
     *
     * @param  int|null  $amount  Refund amount (null = full refund)
     * @param  string  $reason  Refund reason for audit trail
     *
     * @throws ValidationException
     * @throws PaymentGatewayException
     */
    public function execute(Payment $payment, ?int $amount = null, string $reason = ''): Payment
    {
        // Validate payment can be refunded
        $this->validatePaymentCanBeRefunded($payment);

        // Default to full refund
        $refundAmount = $amount ?? $payment->amount;

        // Validate refund amount
        $this->validateRefundAmount($payment, $refundAmount);

        return DB::transaction(function () use ($payment, $refundAmount, $reason) {
            try {
                // Process refund via payment gateway
                $gateway = PaymentGatewayFactory::make($payment->provider->value);
                $refundResult = $gateway->processRefund($payment, $refundAmount, $reason);

                // Update payment status
                $payment->update([
                    'status' => PaymentStatus::Refunded,
                    'raw_payload' => array_merge(
                        $payment->raw_payload ?? [],
                        [
                            'refund' => [
                                'refund_id' => $refundResult['refund_id'],
                                'amount' => $refundAmount,
                                'reason' => $reason,
                                'status' => $refundResult['status'],
                                'refunded_at' => $refundResult['refunded_at']->toDateTimeString(),
                            ],
                        ]
                    ),
                ]);

                // Update invoice if exists
                if ($payment->invoice) {
                    $payment->invoice->update(['status' => 'refunded']);
                }

                // Update related recording sessions
                foreach ($payment->recordingSessions()->get() as $session) {
                    /** @var RecordingSession $session */
                    if ($session->status->value === 'confirmed') {
                        $session->update(['status' => 'cancelled']);
                    }
                }

                Log::info('Payment refund processed successfully', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'refund_id' => $refundResult['refund_id'],
                    'refund_amount' => $refundAmount,
                    'original_amount' => $payment->amount,
                    'reason' => $reason,
                ]);

                // Notify client
                $client = $payment->invoice->client ?? $payment->project->client;
                if ($client) {
                    $client->notify(new RefundProcessedNotification($payment->fresh()));
                }

                return $payment->fresh();
            } catch (\Exception $e) {
                Log::error('Failed to process payment refund', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'refund_amount' => $refundAmount,
                    'error' => $e->getMessage(),
                ]);

                throw PaymentGatewayException::refundFailed($payment->provider->value, $e->getMessage());
            }
        });
    }

    /**
     * Validate payment can be refunded.
     *
     * @throws ValidationException
     */
    protected function validatePaymentCanBeRefunded(Payment $payment): void
    {
        // Must be paid status
        if ($payment->status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => "Cannot refund payment with status: {$payment->status->value}. Only paid payments can be refunded.",
            ]);
        }

        // Must have paid_at timestamp
        if (! $payment->paid_at) {
            throw ValidationException::withMessages([
                'payment' => 'Payment does not have a paid_at timestamp.',
            ]);
        }

        // Check refund eligibility window (optional business rule)
        $refundWindowDays = config('payment.refund_window_days', 90);
        if ($payment->paid_at->addDays($refundWindowDays)->isPast()) {
            throw ValidationException::withMessages([
                'payment' => "Refund window has expired. Payments can only be refunded within {$refundWindowDays} days of payment.",
            ]);
        }
    }

    /**
     * Validate refund amount.
     *
     * @throws ValidationException
     */
    protected function validateRefundAmount(Payment $payment, int $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount must be greater than zero.',
            ]);
        }

        if ($amount > $payment->amount) {
            throw ValidationException::withMessages([
                'amount' => "Refund amount ({$amount}) cannot exceed payment amount ({$payment->amount}).",
            ]);
        }
    }
}
