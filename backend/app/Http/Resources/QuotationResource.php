<?php

namespace App\Http\Resources;

use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Quotation $quotation */
        $quotation = $this->resource;

        return [
            'id' => $quotation->id,
            'name' => $quotation->name,
            'package_display_name' => $quotation->packageDisplayName(),
            'client_id' => $quotation->client_id,
            'specifications' => [
                'track_count' => $quotation->track_count,
                'recording_hours' => $quotation->recording_hours,
                'include_recording' => $quotation->include_recording,
                'include_editing' => $quotation->include_editing,
                'include_mixing' => $quotation->include_mixing,
                'include_mastering' => $quotation->include_mastering,
            ],
            'notes' => $quotation->notes,
            'status' => $quotation->status,
            'status_label' => $quotation->status->label(),
            'auto_price' => $quotation->auto_price,
            'agreed_price' => $quotation->agreed_price,
            'closed_by' => $quotation->closed_by,
            'closed_at' => $quotation->closed_at,
            'created_at' => $quotation->created_at,
            'updated_at' => $quotation->updated_at,
            'latest_offer' => QuotationOfferResource::make($this->whenLoaded('latestOffer')),
            'offers' => QuotationOfferResource::collection($this->whenLoaded('offers')),
            'client' => new UserResource($this->whenLoaded('client')),
        ];
    }
}
