<?php

namespace App\Actions\Notification;

use App\Models\Invoice;
use App\Models\NotificationDelivery;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentCreatedNotification;
use App\Notifications\RefundProcessedNotification;
use Illuminate\Notifications\Notification as NotificationMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DeliverNotification
{
    public function handle(int $deliveryId): void
    {
        $candidate = NotificationDelivery::query()->find($deliveryId);
        if ($candidate === null) {
            return;
        }

        $claimed = DB::transaction(function () use ($candidate): ?NotificationDelivery {
            $recipient = User::query()->whereKey($candidate->recipient_id)->lockForUpdate()->first();
            $delivery = NotificationDelivery::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if (! in_array($delivery->status, ['pending', 'processing'], true)
                || ($delivery->status === 'pending' && $delivery->available_at->isFuture())
                || ($delivery->status === 'processing' && $delivery->lease_until?->isFuture())) {
                return null;
            }

            if ($recipient === null || ! $recipient->is_active) {
                $delivery->update(['status' => 'suppressed', 'lease_token' => null, 'lease_until' => null]);

                return null;
            }

            if ($delivery->attempts >= 3) {
                $delivery->update(['status' => 'failed', 'lease_token' => null, 'lease_until' => null]);

                return null;
            }

            if ($delivery->channel === 'database') {
                $recipient->notifications()->firstOrCreate(['id' => $delivery->notification_id], [
                    'type' => $delivery->notification_type, 'data' => $delivery->payload,
                    'created_at' => $delivery->occurred_at, 'updated_at' => $delivery->occurred_at,
                ]);
                $delivery->update(['status' => 'delivered', 'attempts' => $delivery->attempts + 1,
                    'delivered_at' => now(), 'lease_token' => null, 'lease_until' => null]);

                return null;
            }

            $delivery->update(['status' => 'processing', 'attempts' => $delivery->attempts + 1,
                'lease_token' => (string) Str::uuid(), 'lease_until' => now()->addMinutes(5)]);

            return $delivery;
        });

        if ($claimed === null) {
            return;
        }

        try {
            $recipient = User::query()->find($claimed->recipient_id);
            if ($recipient === null || ! $recipient->is_active) {
                $this->finish($claimed, ['status' => 'suppressed']);

                return;
            }
            Notification::sendNow($recipient, $this->mailNotification($claimed), ['mail']);
            $this->finish($claimed, ['status' => 'delivered', 'delivered_at' => now()]);
        } catch (Throwable) {
            $terminal = $claimed->attempts >= 3;
            $this->finish($claimed, ['status' => $terminal ? 'failed' : 'pending',
                'available_at' => now()->addSeconds($claimed->attempts === 1 ? 60 : 300)]);
            Log::warning('Notification mail delivery failed.', ['delivery_id' => $claimed->id, 'attempt' => $claimed->attempts]);
            if ($terminal) {
                throw new RuntimeException('Notification delivery exhausted its attempts; inspect delivery '.$claimed->id.'.');
            }
        }
    }

    /** @param array<string, mixed> $attributes */
    private function finish(NotificationDelivery $delivery, array $attributes): void
    {
        NotificationDelivery::query()->whereKey($delivery->id)->where('lease_token', $delivery->lease_token)
            ->update([...$attributes, 'lease_token' => null, 'lease_until' => null]);
    }

    private function mailNotification(NotificationDelivery $delivery): NotificationMessage
    {
        if ($delivery->notification_type === BusinessEventNotification::class) {
            return new BusinessEventNotification($delivery->event_key, $delivery->payload['type'], $delivery->payload['metadata']);
        }

        if ($delivery->notification_type === InvoiceIssuedNotification::class) {
            return new InvoiceIssuedNotification((new Invoice)->forceFill($delivery->mail_data));
        }

        $payment = (new Payment)->forceFill($delivery->mail_data);
        if ($delivery->notification_type === PaymentCreatedNotification::class) {
            $payment->payment_url = Payment::query()->whereKey($payment->id)->value('payment_url');
        }

        return match ($delivery->notification_type) {
            PaymentCreatedNotification::class => new PaymentCreatedNotification($payment),
            PaymentConfirmedNotification::class => new PaymentConfirmedNotification($payment),
            RefundProcessedNotification::class => new RefundProcessedNotification($payment),
            default => throw new RuntimeException('Unsupported notification mail type.'),
        };
    }
}
