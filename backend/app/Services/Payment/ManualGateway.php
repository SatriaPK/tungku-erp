<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ManualGateway implements PaymentGatewayInterface
{
    public function createCharge(Payment $payment): array
    {
        $expiresAt = now()->addDays(config('payment.manual.default_expiration_days', 7));

        return [
            'charge_id' => $payment->order_id,
            'payment_url' => null,
            'snap_token' => null,
            'expires_at' => $expiresAt,
        ];
    }

    public function getChargeStatus(string $chargeId): string
    {
        // Manual payments don't have external status
        // Status is managed internally by the application
        return PaymentStatus::Pending->value;
    }

    public function processRefund(Payment $payment, int $amount, string $reason): array
    {
        // Manual refunds are processed offline
        // This just creates a record for tracking
        return [
            'refund_id' => 'manual-refund-'.time(),
            'status' => 'pending',
            'refunded_at' => now(),
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        // Manual gateway doesn't have webhooks
        return true;
    }

    public function parseWebhookPayload(Request $request): array
    {
        return [
            'order_id' => (string) $request->input('order_id', ''),
            'transaction_id' => (string) $request->input('transaction_id', ''),
            'status' => (string) $request->input('status', 'pending'),
            'amount' => (int) $request->input('amount', 0),
            'payment_method' => $request->input('payment_method'),
            'paid_at' => $request->filled('paid_at') ? Carbon::parse($request->input('paid_at')) : null,
        ];
    }

    public function getName(): string
    {
        return 'manual';
    }
}
