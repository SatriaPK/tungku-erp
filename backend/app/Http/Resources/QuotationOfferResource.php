<?php

namespace App\Http\Resources;

use App\Enum\OfferSide;
use App\Models\QuotationOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var QuotationOffer $offer */
        $offer = $this->resource;
        $side = OfferSide::from($offer->getRawOriginal('side'));

        return [
            'id' => $offer->id,
            'quotation_id' => $offer->quotation_id,
            'offered_by' => $offer->offered_by,
            'side' => $side,
            'side_label' => $side->label(),
            'price' => $offer->price,
            'note' => $offer->note,
            'created_at' => $offer->created_at,
        ];
    }
}
