<?php

namespace App\Http\Resources;

use App\Notifications\BusinessEventNotification;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentCreatedNotification;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\RefundProcessedNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use InvalidArgumentException;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     *
     * @scramble-return array{id: string, type: string, data: array{}|array{type: string, payment_id?: int, order_id?: string, amount?: int, invoice_id?: int, invoice_number?: string, schema_version?: int, event_key?: string, title?: string, body?: string, target?: array{type: string, id: int}|null, metadata?: array<string, int|string>}, read_at: ?string, created_at: ?string}
     */
    public function toArray(Request $request): array
    {
        $legacyType = match ($this->type) {
            PaymentCreatedNotification::class => 'payment_created',
            PaymentConfirmedNotification::class => 'payment_confirmed',
            PaymentFailedNotification::class => 'payment_failed',
            RefundProcessedNotification::class => 'refund_processed',
            InvoiceIssuedNotification::class => 'invoice_issued',
            default => null,
        };
        $type = $legacyType ?? 'notification';
        $data = [];
        if ($legacyType !== null) {
            $keys = $legacyType === 'invoice_issued' ? ['invoice_id', 'invoice_number', 'amount'] : ['payment_id', 'order_id', 'amount'];
            $data = ['type' => $legacyType, ...array_intersect_key($this->data, array_flip($keys))];
            foreach ($data as $key => $value) {
                if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                    unset($data[$key]);
                }
            }
        } elseif ($this->type === BusinessEventNotification::class && ($this->data['schema_version'] ?? null) === 1) {
            try {
                $publicType = $this->data['type'] ?? '';
                $metadata = $this->data['metadata'] ?? [];
                if (is_string($publicType) && is_array($metadata)) {
                    $metadata = array_intersect_key($metadata, array_flip([
                        'project_id', 'stage_id', 'track_id', 'submission_id', 'version', 'operator_id',
                        'quotation_id', 'offer_id', 'session_id', 'code', 'status', 'price', 'start_at', 'end_at',
                        'billable_hours', 'hourly_rate', 'extra_fee',
                    ]));
                    $metadata = array_filter($metadata, fn ($value) => is_int($value) || is_string($value));
                    $eventKey = $this->data['event_key'] ?? '';
                    $event = new BusinessEventNotification(is_string($eventKey) ? $eventKey : '', $publicType, $metadata);
                    if ($publicType !== 'recording_extended' || isset($metadata['extra_fee'])) {
                        $data = $event->toArray($request->user());
                        $type = $publicType;
                    }
                }
            } catch (InvalidArgumentException) {
                $data = [];
            }
        }

        return [
            'id' => $this->id, 'type' => $type, 'data' => $data,
            'read_at' => $this->read_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'created_at' => $this->created_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
