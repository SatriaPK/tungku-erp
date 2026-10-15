<?php

namespace App\Models;

use App\Enum\CampaignStatus;
use App\Enum\CRMChannel;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string $message
 * @property int|null $package_id
 * @property Collection<int, CRMChannel> $channels
 * @property CampaignStatus $status
 * @property Carbon|null $sent_at
 * @property int|null $recipients_count
 * @property int|null $pending_recipients_count
 * @property int|null $sent_recipients_count
 * @property int|null $failed_recipients_count
 * @property Carbon|null $created_at
 * @property-read HasMany<CRMCampaignRecipient, $this> $recipients
 */
class CRMCampaign extends Model
{
    protected $table = 'crm_campaigns';

    protected $fillable = [
        'created_by',
        'name',
        'package_id',
        'message',
        'channels',
        'status',
        'sent_at',
    ];

    /**
     * Channels are stored as a JSON enum collection.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'channels' => AsEnumCollection::of(CRMChannel::class),
            'status' => CampaignStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CRMCampaignRecipient::class, 'crm_campaign_id');
    }
}
