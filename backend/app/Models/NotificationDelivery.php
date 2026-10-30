<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array{type: string, payment_id?: int, order_id?: string, invoice_id?: int, invoice_number?: string, amount?: int, schema_version?: int, title?: string, metadata?: array<string, int|string>} $payload
 * @property array{id?: int, order_id?: string, amount?: int, expires_at?: string|null, payment_method?: string|null, invoice_number?: string, total?: int} $mail_data
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $available_at
 * @property CarbonImmutable|null $lease_until
 * @property CarbonImmutable|null $delivered_at
 */
class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array', 'mail_data' => 'array', 'recipient_id' => 'integer',
            'attempts' => 'integer', 'occurred_at' => 'immutable_datetime',
            'available_at' => 'immutable_datetime', 'lease_until' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }
}
