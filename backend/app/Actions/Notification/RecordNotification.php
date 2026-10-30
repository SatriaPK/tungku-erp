<?php

namespace App\Actions\Notification;

use App\Jobs\DeliverNotification;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentCreatedNotification;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\RefundProcessedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RecordNotification
{
    public function handle(
        User $recipient,
        BusinessEventNotification|PaymentCreatedNotification|PaymentConfirmedNotification|PaymentFailedNotification|RefundProcessedNotification|InvoiceIssuedNotification $notification,
    ): void {
        $payload = $notification->toArray($recipient);
        if ($notification instanceof BusinessEventNotification) {
            $key = $notification->eventKey;
            $mailData = [];
        } elseif ($notification instanceof InvoiceIssuedNotification) {
            $key = 'invoice:'.$notification->invoice->id.':issued';
            $mailData = $notification->invoice->only(['id', 'invoice_number', 'total']);
        } else {
            $suffix = match (true) {
                $notification instanceof PaymentCreatedNotification => 'created',
                $notification instanceof PaymentConfirmedNotification => 'paid',
                $notification instanceof PaymentFailedNotification => $notification->payment->status->value,
                default => 'refunded',
            };
            $key = 'payment:'.$notification->payment->id.':'.$suffix;
            $mailData = $notification->payment->only(['id', 'order_id', 'amount', 'payment_method']);
            $mailData['expires_at'] = $notification->payment->expires_at?->format('Y-m-d H:i:s');
        }

        DB::transaction(function () use ($recipient, $notification, $payload, $key, $mailData): void {
            foreach ($notification->via($recipient) as $channel) {
                $delivery = NotificationDelivery::query()->firstOrCreate([
                    'event_key' => $key, 'recipient_id' => $recipient->id, 'channel' => $channel,
                ], [
                    'notification_type' => $notification::class, 'notification_id' => (string) Str::uuid(),
                    'payload' => $payload, 'mail_data' => $mailData, 'occurred_at' => now(),
                    'available_at' => now(), 'status' => $recipient->is_active ? 'pending' : 'suppressed',
                ]);
                if ($delivery->wasRecentlyCreated && $delivery->status === 'pending') {
                    DB::afterCommit(fn () => $this->enqueue($delivery->id));
                }
            }
        });
    }

    public function enqueue(int $deliveryId): void
    {
        try {
            DeliverNotification::dispatch($deliveryId);
        } catch (Throwable) {
            Log::warning('Notification enqueue failed; recovery will retry.', ['delivery_id' => $deliveryId]);
        }
    }
}
