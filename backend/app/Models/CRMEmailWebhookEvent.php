<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CRMEmailWebhookEvent extends Model
{
    protected $table = 'crm_email_webhook_events';

    protected $fillable = [
        'event_id',
        'event_type',
        'provider_message_id',
        'occurred_at',
        'processing_status',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
