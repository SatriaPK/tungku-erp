<?php

namespace App\Http\Resources\Manager;

use App\Models\CRMCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CRMCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CRMCampaign $campaign */
        $campaign = $this->resource;

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'message' => $campaign->message,
            'package_id' => $campaign->package_id,
            'channels' => $campaign->channels->map(fn ($channel): string => $channel->value)->values(),
            'status' => $campaign->status->value,
            'sent_at' => $campaign->sent_at,
            'recipient_counts' => [
                'total' => $campaign->recipients_count,
                'pending' => $campaign->pending_recipients_count,
                'sent' => $campaign->sent_recipients_count,
                'failed' => $campaign->failed_recipients_count,
            ],
            'recipients' => CRMCampaignRecipientResource::collection($campaign->relationLoaded('recipients') ? $campaign->getRelation('recipients') : collect()),
            'created_at' => $campaign->created_at,
        ];
    }
}
