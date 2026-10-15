<?php

namespace App\Http\Resources;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Versi untuk Manager. Katalog klien nanti memakai resource terpisah
 * yang hanya menampilkan harga yang berlaku.
 *
 * @mixin Package
 */
class PackageResource extends JsonResource
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
            'track_count' => $this->track_count,
            'recording_hours' => $this->recording_hours,
            'stages' => array_map(fn ($stage) => $stage->value, $this->includedStages()),
            'include_recording' => $this->include_recording,
            'include_editing' => $this->include_editing,
            'include_mixing' => $this->include_mixing,
            'include_mastering' => $this->include_mastering,
            'auto_price' => $this->auto_price,
            'manual_price' => $this->manual_price,
            'is_price_manual' => $this->is_price_manual,
            'price' => $this->resource->price,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
