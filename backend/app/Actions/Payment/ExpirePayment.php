<?php

namespace App\Actions\Payment;

use App\Actions\Booking\CouponPaymentLifecycle;
use App\Enum\PaymentStatus;
use App\Enum\SessionStatus;
use App\Models\Payment;
use App\Models\RecordingSession;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Expire unpaid payment after expiration time.
 *
 * Usage:
 * app(ExpirePayment::class)->execute($payment);
 */
class ExpirePayment
{
    /**
     * Expire payment and release related resources.
     */
    public function execute(Payment $payment): Payment
    {
        // Only expire if currently pending
        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::PendingManualConfirmation])) {
            Log::warning('Cannot expire payment with current status', [
                'payment_id' => $payment->id,
                'status' => $payment->status->value,
            ]);

            return $payment;
        }

        // Check if actually expired
        if ($payment->expires_at && $payment->expires_at->isFuture()) {
            Log::warning('Attempted to expire payment before expiration time', [
                'payment_id' => $payment->id,
                'expires_at' => $payment->expires_at->toDateTimeString(),
            ]);

            return $payment;
        }

        return DB::transaction(function () use ($payment) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::PendingManualConfirmation])
                || ($payment->expires_at && $payment->expires_at->isFuture())) {
                return $payment;
            }
            // Update payment status
            $payment->update([
                'status' => PaymentStatus::Expired,
            ]);

            app(CouponPaymentLifecycle::class)->release($payment);

            // Release held recording sessions
            foreach ($payment->recordingSessions()->get() as $session) {
                /** @var RecordingSession $session */
                if ($session->status === SessionStatus::Held) {
                    $session->update([
                        'status' => SessionStatus::Expired,
                        'held_until' => null,
                        // 'payment_id' => null, // Leave it for audit record! Or remove as requested? Let's keep consistency with original code for now.
                    ]);
                }
            }

            Log::info('Payment expired successfully', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'expired_at' => now()->toDateTimeString(),
            ]);

            // Notify client
            $client = $payment->invoice->client ?? $payment->project->client;
            if ($client) {
                $client->notify(new PaymentFailedNotification($payment->fresh()));
            }

            return $payment->fresh();
        });
    }

    /**
     * Expire all payments that have passed their expiration time.
     *
     * @return int Number of payments expired
     */
    public function expireAllOverdue(): int
    {
        $payments = Payment::whereIn('status', [
            PaymentStatus::Pending->value,
            PaymentStatus::PendingManualConfirmation->value,
        ])
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($payments as $payment) {
            $this->execute($payment);
            $count++;
        }

        if ($count > 0) {
            Log::info('Batch expired payments', [
                'count' => $count,
                'expired_at' => now()->toDateTimeString(),
            ]);
        }

        return $count;
    }
}
