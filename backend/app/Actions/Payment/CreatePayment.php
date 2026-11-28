<?php

namespace App\Actions\Payment;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\Project;
use App\Notifications\PaymentCreatedNotification;
use App\Services\Payment\PaymentGatewayFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Create a new payment and generate payment link via gateway.
 *
 * Usage:
 * $payment = app(CreatePayment::class)->execute($project, $type, $amount, $provider);
 */
class CreatePayment
{
    /**
     * Create payment and generate gateway charge.
     *
     * @param  Project  $project
     * @param  PaymentType  $type
     * @param  int  $amount  Amount in smallest currency unit (cents/IDR)
     * @param  string|null  $provider  Gateway name (xendit, manual)
     *
     * @throws PaymentGatewayException
     */
    public function execute($project, $type, int $amount, ?string $provider = null): Payment
    {
        // Validate amount
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero');
        }

        // Determine provider (use config default if not specified)
        $provider = $provider ?? config('payment.default_gateway', 'manual');

        if (! PaymentGatewayFactory::supports($provider)) {
            throw new \InvalidArgumentException("Unsupported payment provider: {$provider}");
        }

        return DB::transaction(function () use ($project, $type, $amount, $provider) {
            // Generate unique order ID
            $orderId = $this->generateOrderId();

            // Create payment record
            $payment = Payment::create([
                'project_id' => $project->id,
                'type' => $type,
                'amount' => $amount,
                'status' => PaymentStatus::Pending,
                'provider' => PaymentProvider::from($provider),
                'order_id' => $orderId,
                'expires_at' => $this->calculateExpiration(),
            ]);

            try {
                // Create charge via payment gateway
                $gateway = PaymentGatewayFactory::make($provider);
                $chargeResult = $gateway->createCharge($payment);

                // Update payment with gateway response
                $payment->update([
                    'provider_transaction_id' => $chargeResult['charge_id'],
                    'payment_url' => $chargeResult['payment_url'],
                    'snap_token' => $chargeResult['snap_token'] ?? null,
                    'expires_at' => $chargeResult['expires_at'],
                ]);

                Log::info('Payment created successfully', [
                    'payment_id' => $payment->id,
                    'order_id' => $orderId,
                    'provider' => $provider,
                    'amount' => $amount,
                    'charge_id' => $chargeResult['charge_id'],
                ]);

                if ($project->client) {
                    $project->client->notify(new PaymentCreatedNotification($payment->fresh()));
                }

                return $payment->fresh();
            } catch (\Exception $e) {
                // Log error but keep payment record for audit trail
                Log::error('Failed to create payment gateway charge', [
                    'payment_id' => $payment->id,
                    'order_id' => $orderId,
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ]);

                // Mark payment as failed
                $payment->update(['status' => PaymentStatus::Failed]);

                throw PaymentGatewayException::createChargeFailed($provider, $e->getMessage());
            }
        });
    }

    /**
     * Generate unique order ID in format: PAY-YYYYMMDD-XXXXX
     *
     * Thread-safe implementation using database lock to prevent race conditions.
     */
    protected function generateOrderId(): string
    {
        return DB::transaction(function () {
            $date = now()->format('Ymd');
            $prefix = "PAY-{$date}-";

            // Get last order ID for today with pessimistic lock
            $lastPayment = Payment::where('order_id', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->orderBy('order_id', 'desc')
                ->first();

            // Extract sequence from last order ID or start from 1
            $sequence = $lastPayment
                ? intval(substr($lastPayment->order_id, -5)) + 1
                : 1;

            return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Calculate payment expiration time.
     */
    protected function calculateExpiration(): Carbon
    {
        $hours = config('payment.expiration_hours', 24);

        return now()->addHours($hours);
    }
}
