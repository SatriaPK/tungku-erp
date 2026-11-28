<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enum\PaymentStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Xendit Invoice API (https://docs.xendit.co/apidocs/create-invoice).
 *
 * The client is redirected to Xendit's hosted invoice page, which offers every
 * payment method enabled on the Xendit account (VA, QRIS, e-wallet, card, retail).
 * Test mode is selected by using a "xnd_development_" secret key: the same API
 * then creates simulated invoices that can be paid from the Xendit test dashboard.
 */
class XenditGateway implements PaymentGatewayInterface
{
    /** Xendit only accepts these refund reasons; free text goes into metadata. */
    private const REFUND_REASONS = ['DUPLICATE', 'FRAUDULENT', 'REQUESTED_BY_CUSTOMER', 'CANCELLATION', 'OTHERS'];

    protected string $apiKey;

    protected string $baseUrl;

    protected string $webhookToken;

    public function __construct()
    {
        $this->apiKey = (string) config('payment.xendit.secret_key');
        $this->webhookToken = (string) config('payment.xendit.webhook_token');
        $this->baseUrl = rtrim((string) config('payment.xendit.base_url', 'https://api.xendit.co'), '/');
    }

    public function createCharge(Payment $payment): array
    {
        try {
            $this->ensureConfigured();
            $expirationHours = (int) config('payment.expiration_hours', 24);
            $client = $this->clientOf($payment);

            $customer = array_filter([
                'given_names' => $client?->name,
                'email' => $client?->email,
                'mobile_number' => $this->toE164($client?->phone),
            ]);

            $response = $this->http()
                ->withHeaders(['X-IDEMPOTENCY-KEY' => 'invoice-'.$payment->order_id])
                ->post("{$this->baseUrl}/v2/invoices", array_filter([
                    'external_id' => $payment->order_id,
                    'amount' => $payment->amount,
                    'payer_email' => $client?->email,
                    'description' => $this->buildDescription($payment),
                    'invoice_duration' => $expirationHours * 3600,
                    'success_redirect_url' => config('payment.xendit.success_redirect_url'),
                    'failure_redirect_url' => config('payment.xendit.failure_redirect_url'),
                    'currency' => 'IDR',
                    'items' => $this->buildItems($payment),
                    'customer' => $customer ?: null,
                ], static fn ($value) => $value !== null && $value !== ''));

            if ($response->failed()) {
                throw new \RuntimeException($this->errorMessage($response->json(), $response->status()));
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
                'expires_at' => isset($data['expiry_date']) ? Carbon::parse($data['expiry_date']) : now()->addHours($expirationHours),
            ];
        } catch (\Throwable $e) {
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
            $this->ensureConfigured();
            $response = $this->http()->get("{$this->baseUrl}/v2/invoices/{$chargeId}");

            if ($response->failed()) {
                throw new \RuntimeException($this->errorMessage($response->json(), $response->status()));
            }

            return $this->normalizeStatus((string) $response->json('status'));
        } catch (\Throwable $e) {
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
            $this->ensureConfigured();
            $code = strtoupper(trim($reason));

            $response = $this->http()
                ->withHeaders(['Idempotency-key' => 'refund-'.$payment->order_id.'-'.$amount])
                ->post("{$this->baseUrl}/refunds", [
                    'invoice_id' => $payment->gateway_charge_id,
                    'amount' => $amount,
                    'currency' => 'IDR',
                    'reason' => in_array($code, self::REFUND_REASONS, true) ? $code : 'REQUESTED_BY_CUSTOMER',
                    'metadata' => ['order_id' => $payment->order_id, 'note' => mb_substr($reason, 0, 200)],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException($this->errorMessage($response->json(), $response->status()));
            }

            $data = $response->json();

            Log::info('Xendit refund processed', [
                'order_id' => $payment->order_id,
                'refund_id' => $data['id'],
            ]);

            return [
                'refund_id' => $data['id'],
                'status' => $data['status'],
                'refunded_at' => isset($data['created']) ? Carbon::parse($data['created']) : now(),
            ];
        } catch (\Throwable $e) {
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

        if ($this->webhookToken === '' || ! $callbackToken || ! hash_equals($this->webhookToken, (string) $callbackToken)) {
            Log::warning('Invalid Xendit webhook signature');
            throw new InvalidWebhookSignatureException('Xendit');
        }

        return true;
    }

    public function parseWebhookPayload(Request $request): array
    {
        $status = (string) $request->input('status');
        $paid = in_array(strtoupper($status), ['PAID', 'SETTLED'], true);

        return [
            'order_id' => $request->input('external_id'),
            'transaction_id' => $request->input('id'),
            'status' => $this->normalizeStatus($status),
            'amount' => (int) ($request->input('paid_amount') ?? $request->input('amount')),
            'payment_method' => $request->input('payment_channel') ?? $request->input('payment_method'),
            'paid_at' => $paid ? Carbon::parse($request->input('paid_at') ?? now()) : null,
        ];
    }

    public function getName(): string
    {
        return 'xendit';
    }

    protected function http(): PendingRequest
    {
        return Http::withBasicAuth($this->apiKey, '')->acceptJson()->asJson()->timeout(20)->connectTimeout(10);
    }

    protected function ensureConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('XENDIT_SECRET_KEY belum diatur.');
        }
    }

    protected function clientOf(Payment $payment): ?User
    {
        $payment->loadMissing(['invoice.client', 'project.client']);

        return $payment->invoice?->client ?? $payment->project?->client;
    }

    /**
     * Xendit rejects local numbers such as 0812…; it requires E.164 (+62812…).
     * Unusable numbers are omitted instead of failing the whole invoice.
     */
    protected function toE164(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) ? '+'.$digits : null;
    }

    protected function errorMessage(mixed $body, int $status): string
    {
        $message = is_array($body) ? ($body['message'] ?? $body['error_code'] ?? null) : null;

        return $message ? (string) $message : "HTTP {$status}";
    }

    protected function buildDescription(Payment $payment): string
    {
        $type = $payment->type->label();
        $reference = $payment->project?->code ?? $payment->invoice?->invoice_number ?? $payment->order_id;

        return "Pembayaran {$type} - {$reference}";
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
