<?php

namespace App\Actions\Payment;

use App\Actions\Invoice\EnsureFinalPaymentInvoice;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Models\Payment;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InitiateFinalPayment
{
    public function __construct(private EnsureFinalPaymentInvoice $ensureInvoice) {}

    public function execute(Payment $payment, PaymentProvider $provider): Payment
    {
        $payment->loadMissing('project');

        if ($payment->type !== PaymentType::Final || $payment->amount <= 0) {
            throw new InvalidArgumentException('Only a positive final payment can be initiated.');
        }

        if ($payment->project?->status->value !== 'awaiting_final_payment') {
            throw new InvalidArgumentException('Project is not awaiting final payment.');
        }

        if ($payment->status === PaymentStatus::Paid) {
            throw new InvalidArgumentException('Final payment has already been paid.');
        }

        if ($payment->status === PaymentStatus::PendingManualConfirmation) {
            throw new InvalidArgumentException('Final payment is awaiting manual confirmation.');
        }

        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed, PaymentStatus::Expired], true)) {
            throw new InvalidArgumentException('Final payment cannot be initiated in its current status.');
        }

        if ($payment->amount !== $payment->project->final_payment_amount) {
            throw new InvalidArgumentException('Final payment amount does not match the locked project balance.');
        }

        if ($provider === PaymentProvider::Cash) {
            return DB::transaction(function () use ($payment): Payment {
                $this->ensureInvoice->execute($payment);
                $payment->update([
                    'provider' => PaymentProvider::Cash,
                    'status' => PaymentStatus::PendingManualConfirmation,
                    'payment_url' => null,
                    'snap_token' => null,
                    'expires_at' => null,
                ]);

                return $payment->fresh();
            });
        }

        $this->ensureInvoice->execute($payment);
        $payment->refresh();
        $payment->load('project');

        if ($payment->provider === $provider && $payment->status === PaymentStatus::Pending && $payment->payment_url !== null) {
            return $payment;
        }

        $payment->update([
            'provider' => $provider,
            'status' => PaymentStatus::Pending,
            'order_id' => sprintf('FINAL-%d-%s', $payment->project_id, Str::upper(Str::random(10))),
            'provider_transaction_id' => null,
            'payment_url' => null,
            'snap_token' => null,
            'expires_at' => now()->addHours(config('payment.expiration_hours', 24)),
        ]);

        try {
            $charge = PaymentGatewayFactory::make($provider->value)->createCharge($payment->fresh(['project.client']));
            $payment->update([
                'provider_transaction_id' => $charge['charge_id'],
                'payment_url' => $charge['payment_url'],
                'snap_token' => $charge['snap_token'] ?? null,
                'expires_at' => $charge['expires_at'],
            ]);
        } catch (\Throwable $exception) {
            $payment->update(['status' => PaymentStatus::Failed]);
            report($exception);

            return $payment->fresh();
        }

        return $payment->fresh();
    }
}
