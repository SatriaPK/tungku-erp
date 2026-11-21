<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enum\PaymentStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransGateway implements PaymentGatewayInterface
{
    public function __construct()
    {
        $this->configureApi();
    }

    protected function configureApi(): void
    {
        Config::$serverKey = config('payment.midtrans.server_key');
        Config::$isProduction = config('payment.midtrans.is_production');
        Config::$isSanitized = config('payment.midtrans.is_sanitized');
        Config::$is3ds = config('payment.midtrans.is_3ds');
    }

    public function createCharge(Payment $payment): array
    {
        if (! is_string(config('payment.midtrans.server_key')) || trim((string) config('payment.midtrans.server_key')) === '') {
            throw PaymentGatewayException::createChargeFailed('Midtrans', 'MIDTRANS_SERVER_KEY belum dikonfigurasi.');
        }
        try {
            $params = [
                'transaction_details' => [
                    'order_id' => $payment->order_id,
                    'gross_amount' => $payment->amount,
                ],
                'customer_details' => [
                    'first_name' => $payment->project->client->name ?? $payment->invoice->client->name ?? 'Customer',
                    'email' => $payment->project->client->email ?? $payment->invoice->client->email ?? 'no-reply@example.com',
                    'phone' => $payment->project->client->phone ?? $payment->invoice->client->phone ?? '08000000',
                ],
                'item_details' => $this->buildItemDetails($payment),
                'expiry' => [
                    'duration' => config('payment.expiration_hours'),
                    'unit' => 'hours',
                ],
            ];

            $snapToken = Snap::getSnapToken($params);
            $paymentUrl = config('payment.midtrans.is_production')
                ? "https://app.midtrans.com/snap/v2/vtweb/{$snapToken}"
                : "https://app.sandbox.midtrans.com/snap/v2/vtweb/{$snapToken}";

            Log::info('Midtrans charge created', [
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
            ]);

            return [
                'charge_id' => $payment->order_id,
                'payment_url' => $paymentUrl,
                'snap_token' => $snapToken,
                'expires_at' => now()->addHours(config('payment.expiration_hours')),
            ];
        } catch (\Exception $e) {
            Log::error('Midtrans charge creation failed', [
                'order_id' => $payment->order_id,
                'error' => $e->getMessage(),
            ]);

            throw PaymentGatewayException::createChargeFailed('Midtrans', $e->getMessage());
        }
    }

    public function getChargeStatus(string $chargeId): string
    {
        if (! is_string(config('payment.midtrans.server_key')) || trim((string) config('payment.midtrans.server_key')) === '') {
            throw PaymentGatewayException::statusCheckFailed('Midtrans', $chargeId);
        }
        try {
            $status = (object) Transaction::status($chargeId);

            return $this->normalizeStatus($status->transaction_status, $status->fraud_status ?? null);
        } catch (\Exception $e) {
            Log::error('Midtrans status check failed', [
                'charge_id' => $chargeId,
                'error' => $e->getMessage(),
            ]);

            throw PaymentGatewayException::statusCheckFailed('Midtrans', $chargeId);
        }
    }

    public function processRefund(Payment $payment, int $amount, string $reason): array
    {
        if (! is_string(config('payment.midtrans.server_key')) || trim((string) config('payment.midtrans.server_key')) === '') {
            throw PaymentGatewayException::refundFailed('Midtrans', 'MIDTRANS_SERVER_KEY belum dikonfigurasi.');
        }
        try {
            $params = [
                'refund_key' => 'refund-'.$payment->order_id.'-'.time(),
                'amount' => $amount,
                'reason' => $reason,
            ];

            $result = (object) Transaction::refund($payment->order_id, $params);

            Log::info('Midtrans refund processed', [
                'order_id' => $payment->order_id,
                'refund_amount' => $amount,
            ]);

            return [
                'refund_id' => $result->refund_id ?? $params['refund_key'],
                'status' => $result->status ?? 'pending',
                'refunded_at' => Carbon::parse($result->refund_date ?? now()),
            ];
        } catch (\Exception $e) {
            Log::error('Midtrans refund failed', [
                'order_id' => $payment->order_id,
                'error' => $e->getMessage(),
            ]);

            throw PaymentGatewayException::refundFailed('Midtrans', $e->getMessage());
        }
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $serverKey = config('payment.midtrans.server_key');
        $orderId = $request->input('order_id');
        $statusCode = $request->input('status_code');
        $grossAmount = $request->input('gross_amount');
        $signatureKey = $request->input('signature_key');

        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if (! is_string($signatureKey) || ! hash_equals($expectedSignature, $signatureKey)) {
            Log::warning('Invalid Midtrans webhook signature', ['order_id' => $orderId]);
            throw new InvalidWebhookSignatureException('Midtrans');
        }

        return true;
    }

    public function parseWebhookPayload(Request $request): array
    {
        $transactionStatus = $request->input('transaction_status');
        $fraudStatus = $request->input('fraud_status');

        return [
            'order_id' => $request->input('order_id'),
            'transaction_id' => $request->input('transaction_id'),
            'status' => $this->normalizeStatus($transactionStatus, $fraudStatus),
            'amount' => (int) $request->input('gross_amount'),
            'payment_method' => $request->input('payment_type'),
            'paid_at' => in_array($transactionStatus, ['settlement', 'capture'])
                ? Carbon::parse($request->input('transaction_time'))
                : null,
        ];
    }

    public function getName(): string
    {
        return 'midtrans';
    }

    protected function buildItemDetails(Payment $payment): array
    {
        $type = $payment->type->label();

        $name = "Pembayaran {$type}";
        if ($payment->project) {
            $name .= " - Project {$payment->project->code}";
        } elseif ($payment->invoice) {
            $name .= " - Invoice {$payment->invoice->invoice_number}";
        }

        return [
            [
                'id' => $payment->project_id ?? $payment->invoice_id ?? 'item_1',
                'price' => $payment->amount,
                'quantity' => 1,
                'name' => mb_substr($name, 0, 50),
            ],
        ];
    }

    protected function normalizeStatus(string $transactionStatus, ?string $fraudStatus = null): string
    {
        if ($fraudStatus === 'deny' || $fraudStatus === 'challenge') {
            return PaymentStatus::Failed->value;
        }

        return match ($transactionStatus) {
            'capture', 'settlement' => PaymentStatus::Paid->value,
            'pending' => PaymentStatus::Pending->value,
            'deny', 'cancel', 'failure' => PaymentStatus::Failed->value,
            'expire' => PaymentStatus::Expired->value,
            'refund', 'partial_refund' => PaymentStatus::Refunded->value,
            default => PaymentStatus::Pending->value,
        };
    }
}
