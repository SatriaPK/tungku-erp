<?php

namespace App\Actions\Payment;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class RefundPayment
{
    /**
     * Process full or partial refund for a payment.
     *
     * @throws InvalidArgumentException
     */
    public function execute(Payment $payment, int $amount, string $reason): Payment
    {
        // Validate payment can be refunded
        if ($payment->status !== PaymentStatus::Paid) {
            throw new InvalidArgumentException(
                "Cannot refund payment with status: {$payment->status->value}. Only paid payments can be refunded."
            );
        }

        // Validate refund amount
        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        if ($amount > $payment->amount) {
            throw new InvalidArgumentException(
                "Refund amount ({$amount}) cannot exceed payment amount ({$payment->amount})."
            );
        }

        // Online payments (midtrans/xendit) must process through gateway
        if (in_array($payment->provider, [PaymentProvider::Midtrans, PaymentProvider::Xendit])) {
            return $this->processOnlineRefund($payment, $amount, $reason);
        }

        // Cash payments can be refunded directly
        return $this->processManualRefund($payment, $amount, $reason);
    }

    protected function processOnlineRefund(Payment $payment, int $amount, string $reason): Payment
    {
        try {
            $gateway = PaymentGatewayFactory::make($payment->provider->value);

            $refundResult = $gateway->processRefund($payment, $amount, $reason);

            return DB::transaction(function () use ($payment, $amount, $reason, $refundResult) {
                $payment->update([
                    'status' => PaymentStatus::Refunded,
                    'notes' => "Refunded: {$reason}",
                    'gateway_response' => array_merge(
                        $payment->gateway_response ?? [],
                        ['refund' => $refundResult]
                    ),
                ]);

                Log::info('Payment refunded via gateway', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'amount' => $amount,
                    'refund_id' => $refundResult['refund_id'],
                ]);

                return $payment->fresh();
            });
        } catch (\Exception $e) {
            Log::error('Online refund failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw new InvalidArgumentException(
                "Failed to process refund through {$payment->provider->value}: {$e->getMessage()}"
            );
        }
    }

    protected function processManualRefund(Payment $payment, int $amount, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $amount, $reason) {
            $payment->update([
                'status' => PaymentStatus::Refunded,
                'notes' => "Refunded: {$reason}",
            ]);

            Log::info('Manual payment refunded', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $amount,
            ]);

            return $payment->fresh();
        });
    }
}
