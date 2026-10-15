<?php

namespace App\Models;

use App\Enum\CampaignRecipientStatus;
use App\Enum\CRMChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crm_campaign_id
 * @property int $client_id
 * @property CRMChannel $channel
 * @property CampaignRecipientStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property string|null $error
 * @property-read CRMCampaign $campaign
 * @property-read User $client
 */
class CRMCampaignRecipient extends Model
{
    protected $table = 'crm_campaign_recipients';

    /** @var array<string, string> */
    protected $casts = [
        'channel' => CRMChannel::class,
        'status' => CampaignRecipientStatus::class,
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'last_delivery_event_at' => 'datetime',
    ];

    protected $fillable = [
        'crm_campaign_id',
        'client_id',
        'channel',
        'status',
        'sent_at',
        'delivered_at',
        'provider_message_id',
        'last_delivery_event_at',
        'error',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CRMCampaign::class, 'crm_campaign_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
