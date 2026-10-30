<?php

namespace Database\Factories;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<NotificationDelivery> */
class NotificationDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_key' => 'test:'.Str::uuid(), 'recipient_id' => User::factory(),
            'channel' => 'database', 'notification_type' => PaymentFailedNotification::class,
            'notification_id' => (string) Str::uuid(),
            'payload' => ['type' => 'payment_failed', 'payment_id' => 1, 'order_id' => 'TEST', 'amount' => 100],
            'mail_data' => [], 'occurred_at' => now(), 'available_at' => now(), 'status' => 'pending',
        ];
    }
}
