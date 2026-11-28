<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Http\Controllers\Controller;
use App\Jobs\HandlePaymentWebhook;
use App\Models\WebhookLog;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle payment gateway webhook.
     */
    public function handlePaymentWebhook(Request $request, string $gateway): JsonResponse
    {
        // Validate gateway
        if (! PaymentGatewayFactory::supports($gateway)) {
            Log::warning('Unsupported gateway in webhook', ['gateway' => $gateway]);

            return response()->json(['message' => 'Unsupported gateway'], 400);
        }

        $gatewayService = PaymentGatewayFactory::make($gateway);

        try {
            // Verify webhook signature (throws exception if invalid)
            $gatewayService->verifyWebhookSignature($request);

            // Parse webhook payload
            $parsedData = $gatewayService->parseWebhookPayload($request);

            Log::info('Webhook received', [
                'gateway' => $gateway,
                'order_id' => $parsedData['order_id'],
                'transaction_id' => $parsedData['transaction_id'],
                'status' => $parsedData['status'],
            ]);

            // Log webhook with idempotency protection
            $webhookLog = $this->logWebhook($gateway, $request, $parsedData);

            // Check if already processed (idempotency)
            if ($webhookLog->wasRecentlyCreated === false && $webhookLog->isProcessed()) {
                Log::info('Webhook already processed', [
                    'webhook_log_id' => $webhookLog->id,
                    'order_id' => $parsedData['order_id'],
                ]);

                // Return 200 even for duplicates (idempotent response)
                return response()->json([
                    'status' => 'success',
                    'message' => 'Webhook already processed',
                ], 200);
            }

            // Dispatch job to process webhook asynchronously
            HandlePaymentWebhook::dispatch($webhookLog, $parsedData);

            Log::info('Webhook queued for processing', [
                'webhook_log_id' => $webhookLog->id,
                'order_id' => $parsedData['order_id'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook received and queued',
            ], 200);
        } catch (InvalidWebhookSignatureException $e) {
            Log::error('Invalid webhook signature', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature',
            ], 401);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Log webhook with idempotency protection.
     */
    protected function logWebhook(string $gateway, Request $request, array $parsedData): WebhookLog
    {
        $transactionId = $parsedData['transaction_id'] ?? null;

        // Try to find existing webhook log
        if ($transactionId) {
            $existingLog = WebhookLog::where('gateway', $gateway)
                ->where('transaction_id', $transactionId)
                ->first();

            if ($existingLog) {
                return $existingLog;
            }
        }

        // Create new webhook log
        return WebhookLog::create([
            'gateway' => $gateway,
            'event_type' => $this->extractEventType($gateway, $request),
            'transaction_id' => $transactionId,
            'order_id' => $parsedData['order_id'] ?? null,
            'payload' => $request->all(),
            'headers' => $this->extractHeaders($request),
            'status' => 'received',
        ]);
    }

    /**
     * Extract event type from webhook payload.
     */
    protected function extractEventType(string $gateway, Request $request): ?string
    {
        return match ($gateway) {
            'xendit' => $request->input('status'),
            default => null,
        };
    }

    /**
     * Extract relevant headers for signature verification.
     */
    protected function extractHeaders(Request $request): array
    {
        return [
            'x-callback-token' => $request->header('x-callback-token'),
            'x-signature' => $request->header('x-signature'),
            'content-type' => $request->header('content-type'),
            'user-agent' => $request->header('user-agent'),
        ];
    }
}
