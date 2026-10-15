<?php

namespace App\Jobs;

use App\Actions\Payment\ConfirmPayment;
use App\Actions\Payment\ExpirePayment;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Models\WebhookLog;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class HandlePaymentWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public WebhookLog $webhookLog,
        public array $parsedData
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if already processed (idempotency check)
        if ($this->webhookLog->isProcessed()) {
            Log::info('Webhook already processed, skipping', [
                'webhook_log_id' => $this->webhookLog->id,
                'order_id' => $this->parsedData['order_id'],
            ]);

            return;
        }

        try {
            DB::transaction(function () {
                // Find payment by order_id
                $payment = Payment::query()
                    ->where('order_id', $this->parsedData['order_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    Log::warning('Payment not found for webhook', [
                        'order_id' => $this->parsedData['order_id'],
                        'webhook_log_id' => $this->webhookLog->id,
                    ]);

                    $this->webhookLog->markAsFailed('Payment not found');

                    return;
                }

                // Update payment with gateway response
                $payment->update([
                    'gateway_response' => array_merge(
                        $payment->gateway_response ?? [],
                        ['webhook_'.now()->timestamp => $this->webhookLog->payload]
                    ),
                ]);

                // Handle status transitions
                try {
                    $this->handleStatusTransition($payment);
                } catch (\InvalidArgumentException $exception) {
                    $this->webhookLog->markAsFailed($exception->getMessage());

                    return;
                } catch (ValidationException $exception) {
                    $this->webhookLog->markAsFailed($exception->getMessage());

                    return;
                }

                // Mark webhook as processed
                $this->webhookLog->markAsProcessed();

                Log::info('Webhook processed successfully', [
                    'webhook_log_id' => $this->webhookLog->id,
                    'order_id' => $this->parsedData['order_id'],
                    'status' => $this->parsedData['status'],
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'webhook_log_id' => $this->webhookLog->id,
                'order_id' => $this->parsedData['order_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->webhookLog->markAsFailed($e->getMessage());

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle payment status transition based on webhook data.
     */
    protected function handleStatusTransition(Payment $payment): void
    {
        $newStatus = $this->parsedData['status'];

        // Skip if payment is already in terminal state
        if (in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)) {
            Log::info('Payment already in terminal state, skipping transition', [
                'order_id' => $payment->order_id,
                'current_status' => $payment->status->value,
                'webhook_status' => $newStatus,
            ]);

            return;
        }

        // Process status transitions
        match ($newStatus) {
            PaymentStatus::Paid->value => $this->handlePaidStatus($payment),
            PaymentStatus::Expired->value, PaymentStatus::Failed->value => $this->handleFailedStatus($payment),
            PaymentStatus::Pending->value => Log::info('Pending status, no action needed', [
                'order_id' => $payment->order_id,
            ]),
            default => Log::warning('Unhandled webhook status', [
                'order_id' => $payment->order_id,
                'status' => $newStatus,
            ]),
        };
    }

    /**
     * Handle paid status transition.
     */
    protected function handlePaidStatus(Payment $payment): void
    {
        if ($payment->status === PaymentStatus::Paid) {
            return;
        }

        $webhookAmount = $this->parsedData['amount'] ?? 0;
        if (is_string($webhookAmount) && str_contains($webhookAmount, '.')) {
            $webhookAmount = (int) round((float) $webhookAmount);
        }

        if ((int) $webhookAmount !== $payment->amount) {
            throw new \InvalidArgumentException('Webhook amount does not match payment amount.');
        }

        $confirmPayment = app(ConfirmPayment::class);
        $confirmPayment->execute(
            $payment,
            $this->parsedData['transaction_id'] ?? null,
            $this->parsedData['payment_method'] ?? null,
            $this->webhookLog->payload
        );

        Log::info('Payment confirmed from webhook', [
            'order_id' => $payment->order_id,
            'amount' => $payment->amount,
        ]);
    }

    /**
     * Handle failed/expired status transition.
     */
    protected function handleFailedStatus(Payment $payment): void
    {
        if (in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Expired], true)) {
            return;
        }

        if ($this->parsedData['status'] === PaymentStatus::Failed->value) {
            $payment->update(['status' => PaymentStatus::Failed->value]);

            // Notify client
            $project = $payment->project;
            $client = $project?->client;

            if ($client !== null) {
                $client->notify(new PaymentFailedNotification($payment->fresh()));
            }
        } else {
            $expirePayment = app(ExpirePayment::class);
            $expirePayment->execute($payment);
        }

        Log::info('Payment expired/failed from webhook', [
            'order_id' => $payment->order_id,
            'new_status' => $this->parsedData['status'],
        ]);
    }
}
