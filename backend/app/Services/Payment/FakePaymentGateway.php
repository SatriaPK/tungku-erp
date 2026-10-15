<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FakePaymentGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'fake';
    }

    public function createCharge(Payment $payment): array
    {
        return [
            'charge_id' => 'fake-charge-'.uniqid(),
            'payment_url' => 'https://fake-gateway.test/pay/'.uniqid(),
            'snap_token' => 'fake-snap-token-'.uniqid(),
            'expires_at' => Carbon::now()->addHours(24),
        ];
    }

    public function getChargeStatus(string $chargeId): string
    {
        return 'pending';
    }

    public function processRefund(Payment $payment, int $amount, string $reason): array
    {
        return [
            'refund_id' => 'fake-refund-'.uniqid(),
            'status' => 'success',
            'refunded_at' => Carbon::now(),
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }

    public function parseWebhookPayload(Request $request): array
    {
        $payload = $request->all();

        return [
            'order_id' => $payload['order_id'] ?? 'fake-order-id',
            'transaction_id' => $payload['transaction_id'] ?? 'fake-txn-id',
            'status' => $payload['status'] ?? 'pending',
            'amount' => $payload['amount'] ?? 0,
            'payment_method' => $payload['payment_method'] ?? null,
            'paid_at' => isset($payload['paid_at']) ? Carbon::parse($payload['paid_at']) : null,
        ];
    }
}
