<?php

namespace App\Http\Resources;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Catalog version for clients. Only shows the final price and active packages.
 * Does not expose internal pricing details (auto_price, manual_price, is_price_manual).
 *
 * @mixin Package
 */
class PackageCatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'related_info' => $this->related_info,
            'track_count' => $this->track_count,
            'recording_hours' => $this->recording_hours,
            'include_recording' => $this->include_recording,
            'include_editing' => $this->include_editing,
            'include_mixing' => $this->include_mixing,
            'include_mastering' => $this->include_mastering,
            'price' => $this->resource->price,
        ];
    }
}
