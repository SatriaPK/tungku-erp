<?php

namespace App\Actions\Payment;

use App\Enum\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CancelPayment
{
    /**
     * Cancel a pending payment.
     *
     * @throws InvalidArgumentException
     */
    public function execute(Payment $payment, string $reason): Payment
    {
        // Validate payment can be cancelled
        if ($payment->status !== PaymentStatus::Pending) {
            throw new InvalidArgumentException(
                "Cannot cancel payment with status: {$payment->status->value}. Only pending payments can be cancelled."
            );
        }

        return DB::transaction(function () use ($payment, $reason) {
            $payment->update([
                'status' => PaymentStatus::Cancelled,
                'notes' => "Cancelled: {$reason}",
            ]);

            Log::info('Payment cancelled', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'reason' => $reason,
            ]);

            return $payment->fresh();
        });
    }
}
