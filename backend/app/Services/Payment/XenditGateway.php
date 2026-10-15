<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enum\PaymentStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditGateway implements PaymentGatewayInterface
{
    protected string $apiKey;

    protected string $baseUrl;

    protected string $webhookToken;

    public function __construct()
    {
        $this->apiKey = config('payment.xendit.secret_key') ?? '';
        $this->webhookToken = config('payment.xendit.webhook_token') ?? '';
        $this->baseUrl = 'https://api.xendit.co';
    }

    public function createCharge(Payment $payment): array
    {
        try {
            $expiresAt = now()->addHours(config('payment.expiration_hours'));

            $response = Http::withBasicAuth($this->apiKey, '')
                ->post("{$this->baseUrl}/v2/invoices", [
                    'external_id' => $payment->order_id,
                    'amount' => $payment->amount,
                    'payer_email' => $payment->project->client->email,
                    'description' => $this->buildDescription($payment),
                    'invoice_duration' => config('payment.expiration_hours') * 3600,
                    'success_redirect_url' => config('app.url').'/payment/success',
                    'failure_redirect_url' => config('app.url').'/payment/failed',
                    'currency' => 'IDR',
                    'items' => $this->buildItems($payment),
                    'customer' => [
                        'given_names' => $payment->project->client->name,
                        'email' => $payment->project->client->email,
                        'mobile_number' => $payment->project->client->phone,
                    ],
                ]);

            if ($response->failed()) {
                throw new \Exception($response->json('message', 'Unknown error'));
            }

            $data = $response->json();

            Log::info('Xendit invoice created', [
                'order_id' => $payment->order_id,
                'invoice_id' => $data['id'],
            ]);

            return [
                'charge_id' => $data['id'],
                'payment_url' => $data['invoice_url'],
                'snap_token' => null,
                'expires_at' => $expiresAt,
            ];
        } catch (\Exception $e) {
            Log::error('Xendit invoice creation failed', [
                'order_id' => $payment->order_id,
                'error' => $e->getMessage(),
            ]);

            throw PaymentGatewayException::createChargeFailed('Xendit', $e->getMessage());
        }
    }

    public function getChargeStatus(string $chargeId): string
    {
        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->get("{$this->baseUrl}/v2/invoices/{$chargeId}");

            if ($response->failed()) {
                throw new \Exception($response->json('message', 'Unknown error'));
            }

            $data = $response->json();

            return $this->normalizeStatus($data['status']);
        } catch (\Exception $e) {
            Log::error('Xendit status check failed', [
                'charge_id' => $chargeId,
                'error' => $e->getMessage(),
            ]);

            throw PaymentGatewayException::statusCheckFailed('Xendit', $chargeId);
        }
    }

    public function processRefund(Payment $payment, int $amount, string $reason): array
    {
        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->post("{$this->baseUrl}/refunds", [
                    'invoice_id' => $payment->gateway_charge_id,
                    'amount' => $amount,
                    'reason' => $reason,
                ]);

            if ($response->failed()) {
                throw new \Exception($response->json('message', 'Unknown error'));
            }

            $data = $response->json();

            Log::info('Xendit refund processed', [
                'order_id' => $payment->order_id,
                'refund_id' => $data['id'],
            ]);

            return [
                'refund_id' => $data['id'],
                'status' => $data['status'],
                'refunded_at' => Carbon::parse($data['created']),
            ];
        } catch (\Exception $e) {
            Log::error('Xendit refund failed', [
                'order_id' => $payment->order_id,
                'error' => $e->getMessage(),
            ]);

            throw PaymentGatewayException::refundFailed('Xendit', $e->getMessage());
        }
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $callbackToken = $request->header('x-callback-token');

        if (! $callbackToken || ! hash_equals($this->webhookToken, (string) $callbackToken)) {
            Log::warning('Invalid Xendit webhook signature');
            throw new InvalidWebhookSignatureException('Xendit');
        }

        return true;
    }

    public function parseWebhookPayload(Request $request): array
    {
        $status = $request->input('status');

        return [
            'order_id' => $request->input('external_id'),
            'transaction_id' => $request->input('id'),
            'status' => $this->normalizeStatus($status),
            'amount' => (int) $request->input('amount'),
            'payment_method' => $request->input('payment_method'),
            'paid_at' => $status === 'PAID'
                ? Carbon::parse($request->input('paid_at'))
                : null,
        ];
    }

    public function getName(): string
    {
        return 'xendit';
    }

    protected function buildDescription(Payment $payment): string
    {
        $type = $payment->type->label();

        return "Pembayaran {$type} - Project {$payment->project->code}";
    }

    protected function buildItems(Payment $payment): array
    {
        return [
            [
                'name' => $this->buildDescription($payment),
                'quantity' => 1,
                'price' => $payment->amount,
            ],
        ];
    }

    protected function normalizeStatus(string $xenditStatus): string
    {
        return match (strtoupper($xenditStatus)) {
            'PAID', 'SETTLED' => PaymentStatus::Paid->value,
            'PENDING' => PaymentStatus::Pending->value,
            'EXPIRED' => PaymentStatus::Expired->value,
            default => PaymentStatus::Failed->value,
        };
    }
}
