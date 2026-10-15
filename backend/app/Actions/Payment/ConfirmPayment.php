<?php

namespace App\Actions\Payment;

use App\Actions\Project\SettleFinalPayment;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Enum\SessionStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Models\Payment;
use App\Models\RecordingSession;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Confirm payment as paid (from webhook or manual verification).
 *
 * Usage:
 * app(ConfirmPayment::class)->execute($payment, $transactionId, $paymentMethod);
 */
class ConfirmPayment
{
    /**
     * Confirm payment and update related entities.
     *
     * @param  string|null  $transactionId  Provider transaction ID
     * @param  string|null  $paymentMethod  e.g., 'credit_card', 'bank_transfer'
     * @param  array  $rawPayload  Raw webhook payload for audit
     *
     * @throws ValidationException
     */
    public function execute(
        Payment $payment,
        ?string $transactionId = null,
        ?string $paymentMethod = null,
        array $rawPayload = []
    ): Payment {
        // Validate payment can be confirmed
        $this->validatePaymentCanBeConfirmed($payment);

        return DB::transaction(function () use ($payment, $transactionId, $paymentMethod, $rawPayload) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->project_id !== null) {
                $payment->project()->lockForUpdate()->first();
                $payment->load('project');
            }
            $this->validatePaymentCanBeConfirmed($payment);

            if ($payment->type === PaymentType::Final) {
                if ($payment->invoice === null
                    || $payment->invoice->total !== $payment->amount
                    || $payment->invoice->project_id !== $payment->project_id
                    || $payment->invoice->status->value !== 'issued') {
                    throw ValidationException::withMessages([
                        'payment' => 'Final payment invoice does not match the outstanding payment.',
                    ]);
                }

                $reportedAmount = (int) ($rawPayload['gross_amount'] ?? $rawPayload['amount'] ?? $payment->amount);
                if (str_contains((string) ($rawPayload['gross_amount'] ?? ''), '.')) {
                    $gatewayAmount = (float) $rawPayload['gross_amount'];
                    if ($gatewayAmount !== (float) $payment->amount) {
                        throw new InvalidWebhookSignatureException('Gateway amount does not match final payment amount.');
                    }
                    $reportedAmount = (int) round($gatewayAmount);
                }
                $this->validateFinalPaymentAmount($payment, $reportedAmount);
            }

            // Update payment status
            $payment->update([
                'status' => PaymentStatus::Paid,
                'provider_transaction_id' => $transactionId ?? $payment->provider_transaction_id,
                'payment_method' => $paymentMethod,
                'paid_at' => now(),
                'raw_payload' => empty($rawPayload) ? $payment->raw_payload : $rawPayload,
            ]);

            // Update invoice if exists
            if ($payment->invoice) {
                $payment->invoice->markAsPaid();
            }

            if ($payment->type === PaymentType::Final) {
                app(SettleFinalPayment::class)->execute($payment);
            }

            // Confirm related recording sessions
            foreach ($payment->recordingSessions()->get() as $session) {
                /** @var RecordingSession $session */
                if ($session->status === SessionStatus::Held) {
                    $session->update([
                        'status' => SessionStatus::Confirmed,
                        'held_until' => null,
                    ]);
                }
            }

            Log::info('Payment confirmed successfully', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'transaction_id' => $transactionId,
                'amount' => $payment->amount,
            ]);

            $client = $payment->invoice->client ?? $payment->project->client;
            if ($client) {
                $client->notify(new PaymentConfirmedNotification($payment->fresh()));
            }

            return $payment->fresh();
        });
    }

    /**
     * Validate payment can be confirmed.
     *
     * @throws ValidationException
     */
    protected function validatePaymentCanBeConfirmed(Payment $payment): void
    {
        // Check if already paid
        if ($payment->status === PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => 'Payment has already been confirmed.',
            ]);
        }

        // Check if expired
        if ($payment->status === PaymentStatus::Expired) {
            throw ValidationException::withMessages([
                'payment' => 'Cannot confirm expired payment.',
            ]);
        }

        // Check if cancelled
        if ($payment->status === PaymentStatus::Cancelled) {
            throw ValidationException::withMessages([
                'payment' => 'Cannot confirm cancelled payment.',
            ]);
        }

        // Check if refunded
        if ($payment->status === PaymentStatus::Refunded) {
            throw ValidationException::withMessages([
                'payment' => 'Cannot confirm refunded payment.',
            ]);
        }

        // Check expiration time
        if ($payment->expires_at && $payment->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'payment' => 'Payment has expired. Cannot confirm.',
            ]);
        }

        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::PendingManualConfirmation], true)) {
            throw ValidationException::withMessages([
                'payment' => 'Payment is not awaiting confirmation.',
            ]);
        }
    }

    private function validateFinalPaymentAmount(Payment $payment, int $reportedAmount): void
    {
        $project = $payment->project;

        if ($project === null
            || $project->status !== ProjectStatus::AwaitingFinalPayment
            || $project->final_payment_amount !== $payment->amount
            || $reportedAmount !== $payment->amount) {
            throw ValidationException::withMessages([
                'payment' => 'Nominal pembayaran tidak cocok dengan saldo final terkunci.',
            ]);
        }
    }
}
