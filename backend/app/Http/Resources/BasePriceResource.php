<?php

namespace App\Http\Resources;

use App\Models\BasePrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BasePrice
 */
class BasePriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stage' => $this->stage->value,
            'stage_label' => $this->stage->label(),
            'unit' => $this->unit->value,
            'unit_label' => $this->unit->label(),
            'price' => $this->price,
            'updated_at' => $this->updated_at,
        ];
    }
}