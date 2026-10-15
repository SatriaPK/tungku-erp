<?php

namespace App\Http\Resources\Manager;

use App\Models\CRMCampaignRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CRMCampaignRecipientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CRMCampaignRecipient $recipient */
        $recipient = $this->resource;

        return [
            'id' => $recipient->id,
            'client_id' => $recipient->client_id,
            'client_name' => $recipient->relationLoaded('client') ? $recipient->client->name : null,
            'channel' => $recipient->channel->value,
            'status' => $recipient->status->value,
            'sent_at' => $recipient->sent_at,
            'delivered_at' => $recipient->delivered_at,
            'error' => $recipient->error,
        ];
    }
}
